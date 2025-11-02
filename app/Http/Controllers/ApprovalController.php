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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;


class ApprovalController extends Controller
{
       // Generate nomor urut berikutnya: 001, 002, ...
    protected function generateNextNomorUrut(): string
    {
        $last = \App\Models\Tanah::query()
            ->select(DB::raw("MAX(CASE WHEN nomor_urut REGEXP '^[0-9]+$' THEN CAST(nomor_urut AS UNSIGNED) ELSE 0 END) as max_num"))
            ->value('max_num');

        $next = (int)$last + 1;
        return str_pad((string)$next, 3, '0', STR_PAD_LEFT);
    }


    public function index(Request $r)
    {
        $perPage = (int) $r->input('per_page', 20);
        $status  = $r->input('status');   // opsional: pending | approved | rejected
        $module  = $r->input('module');   // opsional: warga | tanah | bidang
        $action  = $r->input('action');   // opsional: create | update | delete
        $search  = trim((string) $r->input('search', ''));

        // --- base query untuk LIST (paginate)
        $listQ = \App\Models\ApprovalRequest::query();
        if ($module) $listQ->where('module', $module);
        if ($action) $listQ->where('action', $action);

        // default: hanya pending kalau status tidak diisi
        if ($status) {
            $listQ->where('status', $status);
        } else {
            $listQ->where('status', 'pending');
        }

        // Pencarian sederhana (id, target_id, module, action, payload LIKE)
        if ($search !== '') {
            $listQ->where(function ($qq) use ($search) {
                $qq->where('id', $search)
                ->orWhere('target_id', $search)
                ->orWhere('module', 'like', "%{$search}%")
                ->orWhere('action', 'like', "%{$search}%")
                ->orWhere('payload', 'like', "%{$search}%");
            });
        }

        // urut terbaru
        $paginator = $listQ->latest()->paginate($perPage);

        // tambahkan foto_ktp_url (kalau ada) biar FE gampang tampilkan preview
        $paginator->getCollection()->transform(function ($row) {
            $payload = $row->payload ?? [];
            $row->foto_ktp_url = $payload['foto_ktp_url']
                ?? (isset($payload['foto_ktp']) ? url($payload['foto_ktp']) : null);
            return $row;
        });

        // --- base query untuk STATS (harus hormati filter module/action/search YG SAMA,
        //     tapi TANPA filter status, supaya kartu menampilkan total setiap status)
        $statsQ = \App\Models\ApprovalRequest::query();
        if ($module) $statsQ->where('module', $module);
        if ($action) $statsQ->where('action', $action);
        if ($search !== '') {
            $statsQ->where(function ($qq) use ($search) {
                $qq->where('id', $search)
                ->orWhere('target_id', $search)
                ->orWhere('module', 'like', "%{$search}%")
                ->orWhere('action', 'like', "%{$search}%")
                ->orWhere('payload', 'like', "%{$search}%");
            });
        }

        // hitung per status
        $counts = $statsQ
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $stats = [
            'pending'  => (int) ($counts['pending']  ?? 0),
            'approved' => (int) ($counts['approved'] ?? 0),
            'rejected' => (int) ($counts['rejected'] ?? 0),
        ];

        // gabungkan hasil paginator + stats
        $payload = $paginator->toArray();
        $payload['stats'] = $stats;

        return response()->json($payload);
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

   // PATCH: applyTanah – versi tanpa mass-assignment untuk Bidang
    private function applyTanah(\App\Models\ApprovalRequest $ar)
    {
        $p = $ar->payload ?? [];

        if ($ar->action === 'create') {
            return DB::transaction(function () use ($p) {
                // 1) Validasi minimal saat apply (nomor_urut boleh kosong; unik jika ada)
                $data = validator($p, [
                    'nomor_urut'        => ['nullable','string','max:64', Rule::unique('tanah','nomor_urut')],
                    'warga_id'          => ['required','exists:warga,id'],
                    'nama_pemilik_text' => ['nullable','string','max:255'],
                    'keterangan'        => ['nullable','string'],

                    'bidang'              => ['nullable','array','min:1'],
                    'bidang.*.luas_m2'    => ['required_with:bidang','numeric','min:0.01'],
                    'bidang.*.status_hak' => ['required_with:bidang','in:HM,HGB,HP,HGU,HPL,MA,VI,TN'],
                    'bidang.*.penggunaan' => ['required_with:bidang','in:PERUMAHAN,PERDAGANGAN_JASA,PERKANTORAN,INDUSTRI,FASILITAS_UMUM,SAWAH,TEGALAN,PERKEBUNAN,PETERNAKAN_PERIKANAN,HUTAN_BELUKAR,HUTAN_LINDUNG,MUTASI_TANAH,TANAH_KOSONG,LAIN_LAIN'],
                    'bidang.*.keterangan' => ['nullable','string'],

                    // hasil normalisasi dari propose
                    'bidang.*.feature'    => ['nullable','array'],
                    'bidang.*.geojson_id' => ['nullable','exists:geojson,id'],
                    'bidang.*.srid'       => ['nullable','integer','in:4326'],
                    'bidang.*.centroid'   => ['nullable','array','size:2'],
                ])->validate();

                // 2) Generate nomor_urut jika kosong
                $nomorUrut = $data['nomor_urut'] ?? null;
                if (empty($nomorUrut)) {
                    $nomorUrut = $this->generateNextNomorUrut();
                    if (\App\Models\Tanah::where('nomor_urut', $nomorUrut)->exists()) {
                        throw ValidationException::withMessages([
                            'nomor_urut' => 'Nomor urut bentrok, coba ulangi.',
                        ]);
                    }
                }

                // 3) Build atribut Tanah tanpa kolom yang tidak ada (ex: nama_pemilik_text)
                $tanahAttrs = [
                    'nomor_urut' => $nomorUrut,
                    'warga_id'   => $data['warga_id'],
                    'jumlah_m2'  => null, // dihitung setelah bidang dibuat
                    'keterangan' => $data['keterangan'] ?? null,
                ];
                if (Schema::hasColumn('tanah', 'nama_pemilik_text') && !empty($data['nama_pemilik_text'])) {
                    $tanahAttrs['nama_pemilik_text'] = $data['nama_pemilik_text'];
                }

                $t = \App\Models\Tanah::create($tanahAttrs);

                // 4) Insert bidang TANPA mass-assignment (agar status_hak/penggunaan tidak di-drop)
                foreach (($data['bidang'] ?? []) as $b) {
                    $status = strtoupper(trim($b['status_hak']));
                    $guna   = strtoupper(trim($b['penggunaan']));

                    // optional guard, biar tidak jatuh ke default DB
                    $allowedStatus = ['HM','HGB','HP','HGU','HPL','MA','VI','TN'];
                    $allowedGuna = [
                        'PERUMAHAN','PERDAGANGAN_JASA','PERKANTORAN','INDUSTRI','FASILITAS_UMUM',
                        'SAWAH','TEGALAN','PERKEBUNAN','PETERNAKAN_PERIKANAN',
                        'HUTAN_BELUKAR','HUTAN_LINDUNG','MUTASI_TANAH','TANAH_KOSONG','LAIN_LAIN'
                    ];
                    if (!in_array($status, $allowedStatus, true) || !in_array($guna, $allowedGuna, true)) {
                        throw ValidationException::withMessages([
                            'bidang' => 'Status hak / penggunaan tidak valid saat apply.'
                        ]);
                    }

                    $geojsonId = $b['geojson_id'] ?? $this->upsertGeojsonFromPayload($b, null);

                    // ——— INI KUNCI: assign property satu-satu (bukan ::create([...]))
                    $row = new \App\Models\Bidang();
                    $row->tanah_id   = $t->id;
                    $row->geojson_id = $geojsonId;
                    $row->luas_m2    = $b['luas_m2'];
                    $row->status_hak = $status;
                    $row->penggunaan = $guna;
                    $row->keterangan = $b['keterangan'] ?? null;
                    $row->save();
                }

                // 5) Re-calculate jumlah_m2
                $this->recalcJumlahM2($t->id);

                return $t->fresh(['pemilik','bidang']);
            });
        }

        if ($ar->action === 'update') {
            $t = \App\Models\Tanah::findOrFail($ar->target_id);
            $f = $p;

            foreach (['nomor_urut','warga_id','nama_pemilik_text','keterangan'] as $k) {
                if (array_key_exists($k, $f)) $t->{$k} = $f[$k];
            }
            $t->save();

            foreach (($f['bidang_ops'] ?? []) as $op) {
                $verb = $op['op'];
                if ($verb === 'create') {
                    $geojsonId = $op['geojson_id'] ?? $this->upsertGeojsonFromPayload($op, null);

                    $row = new \App\Models\Bidang();
                    $row->tanah_id   = $t->id;
                    $row->geojson_id = $geojsonId;
                    $row->luas_m2    = $op['luas_m2'];
                    $row->status_hak = strtoupper(trim($op['status_hak']));
                    $row->penggunaan = strtoupper(trim($op['penggunaan']));
                    $row->keterangan = $op['keterangan'] ?? null;
                    $row->save();

                } elseif ($verb === 'update') {
                    $b = \App\Models\Bidang::findOrFail($op['id']);
                    if (!empty($op['feature']) || !empty($op['geojson']) || !empty($op['geojson_feature'])) {
                        $b->geojson_id = $this->upsertGeojsonFromPayload($op, $b->geojson_id);
                    }
                    foreach (['luas_m2','status_hak','penggunaan','keterangan'] as $k) {
                        if (array_key_exists($k, $op)) {
                            $b->{$k} = in_array($k, ['status_hak','penggunaan'], true)
                                ? strtoupper(trim($op[$k]))
                                : $op[$k];
                        }
                    }
                    $b->save();

                } elseif ($verb === 'delete') {
                    $b = \App\Models\Bidang::findOrFail($op['id']);
                    $b->delete();
                }
            }

            $this->recalcJumlahM2($t->id);
            return $t->fresh(['pemilik','bidang']);
        }

        if ($ar->action === 'delete') {
            $t = \App\Models\Tanah::findOrFail($ar->target_id);
            $id = $t->id;
            $t->delete();
            return ['deleted_tanah_id' => $id];
        }

        throw new \InvalidArgumentException('Aksi tanah tidak dikenali.');
    }

    // ... (bagian update/delete tetap seperti punyamu, tak perlu diubah)



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
            return DB::transaction(function () use ($p) {
                // 1) Validasi tanah_id
                $tanahId = (int) ($p['tanah_id'] ?? 0);
                if ($tanahId <= 0 || !Tanah::whereKey($tanahId)->exists()) {
                    throw new \RuntimeException("Tanah tidak ditemukan (tanah_id={$tanahId}).");
                }

                // 2) Pastikan kita punya geojson_id (boleh dari geometry/geojson/feature)
                $geojsonId = $p['geojson_id'] ?? $this->upsertGeojsonFromPayload($p, null);

                // 3) Buat bidang TANPA mass-assignment
                $b = new Bidang();
                $b->tanah_id   = $tanahId;
                $b->geojson_id = $geojsonId ?: null;
                $b->luas_m2    = $p['luas_m2'] ?? 0;

                if (isset($p['status_hak'])) {
                    $b->status_hak = strtoupper($p['status_hak']); // HM/HGB/…
                }
                if (isset($p['penggunaan'])) {
                    $b->penggunaan = strtoupper($p['penggunaan']); // PERUMAHAN/…
                }

                $b->keterangan = $p['keterangan'] ?? null;
                $b->save();

                // 4) Recalc luas tanah
                $this->recalcJumlahM2($tanahId);

                return $b->fresh(['geojson']);
            });
        }

        if ($ar->action === 'update') {
            return DB::transaction(function () use ($p, $ar) {
                $b = Bidang::findOrFail($ar->target_id);

                // Perbarui geometry kalau ada bentuk apa pun
                if (!empty($p['feature']) || !empty($p['geojson']) || !empty($p['geojson_feature']) || !empty($p['geometry'])) {
                    $b->geojson_id = $this->upsertGeojsonFromPayload($p, $b->geojson_id);
                }

                if (array_key_exists('luas_m2', $p)) {
                    $b->luas_m2 = $p['luas_m2'];
                }
                if (array_key_exists('status_hak', $p)) {
                    $b->status_hak = strtoupper($p['status_hak']);
                }
                if (array_key_exists('penggunaan', $p)) {
                    $b->penggunaan = strtoupper($p['penggunaan']);
                }
                if (array_key_exists('keterangan', $p)) {
                    $b->keterangan = $p['keterangan'];
                }

                $b->save();

                $this->recalcJumlahM2($b->tanah_id);
                return $b->fresh(['geojson']);
            });
        }

        if ($ar->action === 'delete') {
            return DB::transaction(function () use ($ar) {
                $b = Bidang::findOrFail($ar->target_id);
                $tanahId = $b->tanah_id;
                $b->delete();
                $this->recalcJumlahM2($tanahId);
                return true;
            });
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
