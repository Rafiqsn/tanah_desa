<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\Tanah;
use App\Models\Warga;
use App\Models\Bidang;
use App\Models\Geojson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApprovalController extends Controller
{
    public function index(Request $r)
    {
        $q = ApprovalRequest::where('status', 'pending');
        if ($r->filled('module')) $q->where('module', $r->input('module'));
        if ($r->filled('action')) $q->where('action', $r->input('action'));

        return response()->json($q->orderBy('id')->paginate(20));
    }

    public function approve(Request $r, $id)
    {
        $ar = ApprovalRequest::findOrFail($id);
        if ($ar->status !== 'pending') {
            return response()->json(['message' => 'Sudah diproses'], 409);
        }

        try {
            $result = DB::transaction(function () use ($ar) {
                return match ($ar->module) {
                    'tanah'  => $this->applyTanah($ar),
                    'warga'  => $this->applyWarga($ar),
                    'bidang' => $this->applyBidang($ar),
                    default  => throw new \InvalidArgumentException('Unknown module'),
                };
            });

            $ar->update([
                'status'      => 'approved',
                'reviewed_by' => request()->user()->id,
                'reviewed_at' => now(),
                'review_note' => request('note'),
            ]);

            return response()->json(['message' => 'Disetujui', 'result' => $result]);
        } catch (\Throwable $e) {
            report($e);
            $ar->update([
                'status'      => 'rejected',
                'reviewed_by' => $r->user()->id,
                'reviewed_at' => now(),
                'review_note' => trim((string)request('note').' | '.$e->getMessage()),
            ]);
            return response()->json([
                'message' => 'Apply gagal, proposal ditolak',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    public function reject(Request $r, $id)
    {
        $ar = ApprovalRequest::findOrFail($id);
        if ($ar->status !== 'pending') {
            return response()->json(['message' => 'Sudah diproses'], 409);
        }

        $ar->update([
            'status'      => 'rejected',
            'reviewed_by' => $r->user()->id,
            'reviewed_at' => now(),
            'review_note' => $r->input('note'),
        ]);

        return response()->json(['message' => 'Proposal ditolak']);
    }

    /* ================== APPLY: TANAH ================== */

    private function applyTanah(ApprovalRequest $ar)
    {
        $p = $ar->payload ?? [];

        if ($ar->action === 'create') {
            $t = Tanah::create([
                'nomor_urut' => $p['nomor_urut'],
                'warga_id'   => $p['warga_id'] ?? null,
                'jumlah_m2'  => null, // dihitung setelah bidang dibuat
                'keterangan' => $p['keterangan'] ?? null,
            ]);

            foreach (($p['bidang'] ?? []) as $b) {
                $geojsonId = $b['geojson_id'] ?? $this->upsertGeojsonFromPayload($b, null);

                Bidang::create([
                    'tanah_id'    => $t->id,
                    'geojson_id'  => $geojsonId,
                    'luas_m2'     => $b['luas_m2'],
                    'status_hak'  => $b['status_hak'],
                    'penggunaan'  => $b['penggunaan'],
                    'keterangan'  => $b['keterangan'] ?? null,
                ]);
            }

            $this->recalcJumlahM2($t->id);
            return $t->fresh(['pemilik','bidang']);
        }

        if ($ar->action === 'update') {
            $t = Tanah::findOrFail($ar->target_id);
            $f = $p;

            foreach (['nomor_urut','warga_id','nama_pemilik_text','keterangan'] as $k) {
                if (array_key_exists($k, $f)) $t->{$k} = $f[$k];
            }
            $t->save();

            foreach (($f['bidang_ops'] ?? []) as $op) {
                $verb = $op['op'];
                if ($verb === 'create') {
                    $geojsonId = $op['geojson_id'] ?? $this->upsertGeojsonFromPayload($op, null);
                    Bidang::create([
                        'tanah_id'    => $t->id,
                        'geojson_id'  => $geojsonId,
                        'luas_m2'     => $op['luas_m2'],
                        'status_hak'  => $op['status_hak'],
                        'penggunaan'  => $op['penggunaan'],
                        'keterangan'  => $op['keterangan'] ?? null,
                    ]);
                } elseif ($verb === 'update') {
                    $b = Bidang::findOrFail($op['id']);
                    if (!empty($op['feature']) || !empty($op['geojson']) || !empty($op['geojson_feature'])) {
                        $b->geojson_id = $this->upsertGeojsonFromPayload($op, $b->geojson_id);
                    }
                    foreach (['luas_m2','status_hak','penggunaan','keterangan'] as $k) {
                        if (array_key_exists($k, $op)) $b->{$k} = $op[$k];
                    }
                    $b->save();
                } elseif ($verb === 'delete') {
                    $b = Bidang::findOrFail($op['id']);
                    $b->delete();
                }
            }

            $this->recalcJumlahM2($t->id);
            return $t->fresh(['pemilik','bidang']);
        }

        if ($ar->action === 'delete') {
            $t = Tanah::findOrFail($ar->target_id);
            $id = $t->id;
            $t->delete();
            return ['deleted_tanah_id' => $id];
        }

        throw new \InvalidArgumentException('Aksi tanah tidak dikenali.');
    }

    /* ================== APPLY: WARGA ================== */

    private function applyWarga(ApprovalRequest $ar)
    {
        $p = $ar->payload ?? [];

        if ($ar->action === 'create') {
            return Warga::create($p);
        }

        if ($ar->action === 'update') {
            $w = Warga::findOrFail($ar->target_id);
            $w->fill($p);
            $w->save();
            return $w;
        }

        if ($ar->action === 'delete') {
            $w = Warga::findOrFail($ar->target_id);
            $id = $w->id;
            $w->delete();
            return ['deleted_warga_id' => $id];
        }

        throw new \InvalidArgumentException('Aksi warga tidak dikenali.');
    }

    /* ================== APPLY: BIDANG ================== */

    private function applyBidang(ApprovalRequest $ar)
    {
        $p = $ar->payload ?? [];

        if ($ar->action === 'create') {
            $geojsonId = $p['geojson_id'] ?? $this->upsertGeojsonFromPayload($p, null);

            $b = Bidang::create([
                'tanah_id'    => $p['tanah_id'],
                'geojson_id'  => $geojsonId,
                'luas_m2'     => $p['luas_m2'],
                'status_hak'  => $p['status_hak'],
                'penggunaan'  => $p['penggunaan'],
                'keterangan'  => $p['keterangan'] ?? null,
            ]);

            $this->recalcJumlahM2($p['tanah_id']);
            return $b;
        }

        if ($ar->action === 'update') {
            $b = Bidang::findOrFail($ar->target_id);

            if (!empty($p['feature']) || !empty($p['geojson']) || !empty($p['geojson_feature'])) {
                $b->geojson_id = $this->upsertGeojsonFromPayload($p, $b->geojson_id);
            }

            foreach (['luas_m2','status_hak','penggunaan','keterangan'] as $k) {
                if (array_key_exists($k, $p)) $b->{$k} = $p[$k];
            }
            $b->save();

            $this->recalcJumlahM2($b->tanah_id);
            return $b;
        }

        if ($ar->action === 'delete') {
            $b = Bidang::findOrFail($ar->target_id);
            $tanahId = $b->tanah_id;
            $b->delete();
            $this->recalcJumlahM2($tanahId);
            return true;
        }

        throw new \InvalidArgumentException('Aksi bidang tidak dikenali.');
    }

    /* ================== HELPERS ================== */

    /**
     * Simpan/update ke tabel geojson pakai kolom:
     * - feature_json (JSON string), srid, nama (opsional), centroid_lng, centroid_lat
     * Return: geojson_id.
     */
    private function upsertGeojsonFromPayload(array $payload, ?int $existingId): int
    {
        // Ambil Feature dari beberapa kemungkinan key:
        $feature = $payload['feature'] ?? $payload['geojson'] ?? $payload['geojson_feature'] ?? null;
        if (!$feature) {
            if ($existingId) return $existingId;
            throw new \InvalidArgumentException('feature/geojson wajib ada.');
        }

        // Pastikan berbentuk Feature
        if (($feature['type'] ?? null) !== 'Feature') {
            $feature = [
                'type'       => 'Feature',
                'properties' => (object)[],
                'geometry'   => $feature,
            ];
        }

        $attrs = [
            'nama'         => $payload['geojson_nama'] ?? null,
            'feature_json' => json_encode($feature),
            'srid'         => (int)($payload['srid'] ?? 4326),
            'centroid_lng' => $payload['centroid'][0] ?? null,
            'centroid_lat' => $payload['centroid'][1] ?? null,
            // 'properties' => null, // kalau mau simpan ekstra metadata
        ];

        if ($existingId) {
            $g = Geojson::find($existingId);
            if ($g) {
                $g->update($attrs);
                return $g->id;
            }
        }

        $g = Geojson::create($attrs);
        return $g->id;
    }

    /** Hitung ulang jumlah_m2 = SUM(luas_m2) bidang aktif */
    private function recalcJumlahM2(int $tanahId): void
    {
        $sum = (float) Bidang::where('tanah_id', $tanahId)->whereNull('deleted_at')->sum('luas_m2');
        Tanah::where('id', $tanahId)->update(['jumlah_m2' => $sum]);
    }
}
