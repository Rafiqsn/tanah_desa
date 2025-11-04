<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class ApprovalAuditController extends Controller
{
    /**
     * GET /api/audit/approvals
     * Query (opsional):
     * status, module, action, submitted_by, reviewed_by(=ID|'me'),
     * month, year  ATAU  date_from, date_to, q, per_page, include_payload
     */
    public function index(Request $r)
    {
        // Base query + relasi user (nama & email)
        $base = ApprovalRequest::query()
            ->whereIn('status', ['approved', 'rejected'])
            ->with(['submitter:id,name,email', 'reviewer:id,name,email']);

        $this->applyFilters($base, $r);

        // ----- LIST (paginate) -----
        $perPage = max(1, min((int) $r->input('per_page', 12), 100));
        $listQ   = (clone $base)->orderByDesc('reviewed_at')->orderByDesc('id');
        $p       = $listQ->paginate($perPage);

        $includePayload = $r->boolean('include_payload', false);
        $rows = $p->getCollection()->values()->map(function (ApprovalRequest $a, $i) use ($p, $includePayload) {
            $labelMap = ['create' => 'Tambah', 'update' => 'Edit', 'delete' => 'Hapus'];

            return [
                'no'              => $p->firstItem() + $i,
                'id'              => $a->id,
                'module'          => $a->module,
                'action'          => $a->action,
                'jenis_perubahan' => $labelMap[$a->action] ?? strtoupper($a->action),
                'status'          => $a->status,
                'target_id'       => $a->target_id,
                'review_note'     => $a->review_note,
                'submitted_at'    => optional($a->created_at)?->toIso8601String(),
                'reviewed_at'     => optional($a->reviewed_at)?->toIso8601String(),
                'submitted_by'    => [
                    'id'    => $a->submitted_by,
                    'name'  => optional($a->submitter)->name,
                    'email' => optional($a->submitter)->email,
                ],
                'reviewed_by'     => [
                    'id'    => $a->reviewed_by,
                    'name'  => optional($a->reviewer)->name,
                    'email' => optional($a->reviewer)->email,
                ],
                'payload'         => $includePayload ? $a->payload : null,
            ];
        });

        // ----- STATS (untuk header ringkasan & grafik) -----
        $stats   = $this->computeStats($base);
        $summary = [
            'total'  => $stats['total'],
            'create' => $stats['by_action']['create'] ?? 0,
            'update' => $stats['by_action']['update'] ?? 0,
            'delete' => $stats['by_action']['delete'] ?? 0,
        ];

        return response()->json([
            'filters'    => [
                'module' => $r->input('module'),
                'action' => $r->input('action'),
                'status' => $r->input('status'),
                'month'  => $r->input('month'),
                'year'   => $r->input('year'),
                'date_from' => $r->input('date_from'),
                'date_to'   => $r->input('date_to'),
                'q'      => $r->input('q'),
            ],
            'summary'    => $summary,        // cocok untuk kalimat "Total X perubahan ..."
            'pagination' => [
                'current_page' => $p->currentPage(),
                'per_page'     => $p->perPage(),
                'total'        => $p->total(),
                'last_page'    => $p->lastPage(),
                'from'         => $p->firstItem(),
                'to'           => $p->lastItem(),
            ],
            'data'       => $rows,           // tabel
            'stats'      => $stats,          // untuk chart (by_action, by_status, per_day, dst)
        ]);
    }

    /** Terapkan semua filter yang sama di list & stats */
    private function applyFilters(Builder $q, Request $r): void
    {
        if ($r->filled('status')) {
            $statuses = collect(explode(',', $r->input('status')))
                ->map(fn ($s) => strtolower(trim($s)))
                ->intersect(['approved','rejected'])
                ->all();
            if ($statuses) $q->whereIn('status', $statuses);
        }

        if ($r->filled('module'))       $q->where('module', $r->input('module'));
        if ($r->filled('action'))       $q->where('action', $r->input('action'));
        if ($r->filled('submitted_by')) $q->where('submitted_by', (int) $r->input('submitted_by'));

        if ($r->filled('reviewed_by')) {
            $rb = $r->input('reviewed_by');
            $q->where('reviewed_by', $rb === 'me' ? $r->user()->id : (int) $rb);
        }

        // Periode bulan/tahun (seperti UI) atau rentang bebas
        if ($r->filled('month') && $r->filled('year')) {
            $q->whereYear('reviewed_at', (int) $r->input('year'))
              ->whereMonth('reviewed_at', (int) $r->input('month'));
        } else {
            if ($r->filled('date_from')) $q->whereDate('reviewed_at', '>=', $r->input('date_from'));
            if ($r->filled('date_to'))   $q->whereDate('reviewed_at', '<=', $r->input('date_to'));
        }

        if ($r->filled('q')) {
            $term = '%'.$r->input('q').'%';
            $q->where(function ($w) use ($term) {
                $w->where('module', 'like', $term)
                  ->orWhere('action', 'like', $term)
                  ->orWhere('review_note', 'like', $term);
            });
        }
    }

    /** Hitung statistik agregat untuk dashboard */
    private function computeStats(Builder $base): array
    {
        $total = (clone $base)->count();

        $byAction = (clone $base)
            ->select('action', DB::raw('COUNT(*) as total'))
            ->groupBy('action')->pluck('total','action')->toArray();

        $byStatus = (clone $base)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')->pluck('total','status')->toArray();

        // Reviewer (dengan nama)
        $byReviewer = (clone $base)
            ->leftJoin('users as ru', 'ru.id', '=', 'approval_requests.reviewed_by')
            ->select('approval_requests.reviewed_by as id', 'ru.name', DB::raw('COUNT(*) as total'))
            ->groupBy('approval_requests.reviewed_by', 'ru.name')
            ->orderByDesc('total')
            ->get();

        // Per hari (untuk grafik area/bar)
        $perDay = (clone $base)
            ->select(DB::raw('DATE(reviewed_at) as day'), 'status', DB::raw('COUNT(*) as total'))
            ->groupBy(DB::raw('DATE(reviewed_at)'), 'status')
            ->orderBy('day', 'asc')
            ->get();

        return [
            'total'       => $total,
            'by_action'   => $byAction,   // ['create'=>x,'update'=>y,'delete'=>z]
            'by_status'   => $byStatus,   // ['approved'=>a,'rejected'=>b]
            'by_reviewer' => $byReviewer, // [{id,name,total}, ...]
            'per_day'     => $perDay,     // [{day, status, total}, ...]
        ];
    }

        public function show(Request $r, $id)
    {
        $a = ApprovalRequest::whereIn('status', ['approved', 'rejected'])
            ->with(['submitter:id,name,email', 'reviewer:id,name,email'])
            ->findOrFail($id);

        $labelMap = ['create' => 'Tambah', 'update' => 'Edit', 'delete' => 'Hapus'];

        $payload = null;
        if ($r->boolean('include_payload', false)) {
            $payload = $a->payload; // kirim yang ada dulu

            // ⬇️ fallback jika kosong
            if (is_null($payload)) {
                switch ($a->module) {
                    case 'warga':
                        if ($w = \App\Models\Warga::find($a->target_id)) {
                            $payload = ['after' => [
                                'nama_pemilik'     => $w->nama_lengkap,
                                'nomor_urut'       => null,
                                'jumlah_luas'      => null,
                                'status_hak_tanah' => null,
                                'penggunaan_tanah' => [],
                                'keterangan'       => $w->keterangan,
                            ]];
                        }
                        break;
                    case 'tanah':
                        if ($t = \App\Models\Tanah::with('pemilik')->find($a->target_id)) {
                            $payload = ['after' => [
                                'nama_pemilik'     => optional($t->pemilik)->nama_lengkap,
                                'nomor_urut'       => (string)$t->nomor_urut,
                                'jumlah_luas'      => (string)$t->jumlah_m2,
                                'status_hak_tanah' => null,
                                'penggunaan_tanah' => [],
                                'keterangan'       => $t->keterangan,
                            ]];
                        }
                        break;
                    case 'bidang':
                        if ($b = \App\Models\Bidang::with('tanah.pemilik')->find($a->target_id)) {
                            $payload = ['after' => [
                                'nama_pemilik'     => optional(optional($b->tanah)->pemilik)->nama_lengkap,
                                'nomor_urut'       => (string)optional($b->tanah)->nomor_urut,
                                'jumlah_luas'      => (string)$b->luas_m2,
                                'status_hak_tanah' => $b->status_hak,
                                'penggunaan_tanah' => is_string($b->penggunaan)&&$b->penggunaan!=='' ? [$b->penggunaan] : (array)$b->penggunaan,
                                'keterangan'       => $b->keterangan,
                            ]];
                        }
                        break;
                }
            }
        }
        $includePayload = $r->boolean('include_payload', false);
        $payloadOut = null;

        if ($includePayload) {
            $p = $a->payload;

            // kalau payload flat untuk create WARGA → bungkus sebagai "after" + mapping ke field UI
            if ($a->module === 'warga' && $a->action === 'create' && is_array($p)) {
                $payloadOut = [
                    'after' => [
                        'nama_pemilik'     => $p['nama_lengkap'] ?? null,
                        'nomor_urut'       => null,
                        'jumlah_luas'      => null,
                        'status_hak_tanah' => null,
                        'penggunaan_tanah' => [],
                        'keterangan'       => $p['keterangan'] ?? null, // kalau ada
                    ],
                ];
            }

            // kalau kamu ingin dukung semua module/action:
            if (is_null($payloadOut)) {
                // sudah proper before/after? → kirim seperti biasa
                if (isset($p['before']) || isset($p['after']) || isset($p['old']) || isset($p['new'])) {
                    $before = $p['before'] ?? $p['old'] ?? null;
                    $after  = $p['after']  ?? $p['new'] ?? null;
                    $payloadOut = [
                        'before' => is_array($before) ? $before : null,
                        'after'  => is_array($after)  ? $after  : null,
                    ];
                } else {
                    // default best-effort untuk payload flat selain warga/create
                    $payloadOut = is_array($p) ? ['after' => $p] : null;
                }
            }
        }

        return response()->json([
            'id'               => $a->id,
            'module'           => $a->module,
            'action'           => $a->action,
            'jenis_perubahan'  => $labelMap[$a->action] ?? strtoupper($a->action),
            'status'           => $a->status,
            'target_id'        => $a->target_id,
            'payload' => $includePayload ? $payloadOut : null,
 // ← sekarang bisa terisi
            'review_note'      => $a->review_note,
            'submitted_at'     => optional($a->created_at)?->toIso8601String(),
            'reviewed_at'      => optional($a->reviewed_at)?->toIso8601String(),
            'submitted_by'     => [
                'id'    => $a->submitted_by,
                'name'  => optional($a->submitter)->name,
                'email' => optional($a->submitter)->email,
            ],
            'reviewed_by'      => [
                'id'    => $a->reviewed_by,
                'name'  => optional($a->reviewer)->name,
                'email' => optional($a->reviewer)->email,
            ],
        ]);
    }

            private function mapBidangToPayload(\App\Models\Bidang $b): array
        {
            $owner = optional(optional($b->tanah)->pemilik);
            return [
                'nama_pemilik'     => $owner->nama_lengkap ?? null,
                'nomor_urut'       => (string) optional($b->tanah)->nomor_urut,
                'jumlah_luas'      => (string) $b->luas_m2,
                'status_hak_tanah' => $b->status_hak, // HM/HGB/HP/HGU/...
                'penggunaan_tanah' => $this->penggunaanToArray($b->penggunaan),
                'keterangan'       => $b->keterangan,
            ];
        }

        private function mapTanahToPayload(\App\Models\Tanah $t): array
        {
            $owner = optional($t->pemilik);
            return [
                'nama_pemilik'     => $owner->nama_lengkap ?? null,
                'nomor_urut'       => (string) $t->nomor_urut,
                'jumlah_luas'      => (string) $t->jumlah_m2,
                'status_hak_tanah' => null, // biasanya status per-bidang; biarkan null
                'penggunaan_tanah' => [],   // idem
                'keterangan'       => $t->keterangan,
            ];
        }

        private function mapWargaToPayload(\App\Models\Warga $w): array
        {
            return [
                'nama_pemilik'     => $w->nama_lengkap,
                'nomor_urut'       => null,
                'jumlah_luas'      => null,
                'status_hak_tanah' => null,
                'penggunaan_tanah' => [],
                'keterangan'       => $w->keterangan,
            ];
        }

        private function penggunaanToArray($penggunaan): array
        {
            // Jika enum string (schema baru) → bungkus jadi array
            if (is_string($penggunaan) && $penggunaan !== '') {
                return [$penggunaan];
            }
            // Jika struktur lama: mapping dari kolom boolean → array label (sesuaikan kalau masih dipakai)
            // return array_keys(array_filter([
            //     'SAWAH' => (bool) $penggunaan_sawah,
            //     'TEGALAN' => (bool) $penggunaan_tegalan,
            //     ...
            // ]));
            return (array) $penggunaan;
        }

    private function normalizePayloadForFE(ApprovalRequest $a): ?array
    {
        // 1) kalau sudah proper before/after → kirim apa adanya (ditambah mapping field)
        $p = $a->payload;
        if (is_array($p) && (isset($p['before']) || isset($p['after']) || isset($p['old']) || isset($p['new']))) {
            $before = $p['before'] ?? $p['old'] ?? null;
            $after  = $p['after']  ?? $p['new'] ?? null;
            return [
                'before' => is_array($before) ? $this->mapModuleFields($a->module, $before) : null,
                'after'  => is_array($after)  ? $this->mapModuleFields($a->module, $after)  : null,
            ];
        }

        // 2) kalau payload flat (array biasa) → bungkus sesuai action
        if (is_array($p) && !isset($p['before']) && !isset($p['after']) && !isset($p['old']) && !isset($p['new'])) {
            $snap = $this->mapModuleFields($a->module, $p);
            return match ($a->action) {
                'create' => ['after' => $snap],
                'delete' => ['before' => $snap],
                default  => ['after' => $snap], // update (best effort kalau tidak ada 'old')
            };
        }

        // 3) payload null → coba snapshot current target sebagai 'after' (biar FE tidak "-")
        if (empty($p)) {
            switch ($a->module) {
                case 'bidang':
                    $b = \App\Models\Bidang::with('tanah.pemilik')->find($a->target_id);
                    return $b ? ['after' => $this->mapBidangToFE($b)] : null;
                case 'tanah':
                    $t = \App\Models\Tanah::with('pemilik')->find($a->target_id);
                    return $t ? ['after' => $this->mapTanahToFE($t)] : null;
                case 'warga':
                    $w = \App\Models\Warga::find($a->target_id);
                    return $w ? ['after' => $this->mapWargaToFE($w)] : null;
            }
        }

        return null;
    }

    // ==== Mapper: ubah struktur payload mentah jadi field yang FE pakai ====
    // Kalau payload flat dari form, isi beberapa kemungkinan nama kolom.
    private function mapModuleFields(string $module, array $raw): array
    {
        return match ($module) {
            'bidang' => [
                'nama_pemilik'     => $raw['nama_pemilik'] ?? $raw['pemilik'] ?? $raw['warga_nama'] ?? null,
                'nomor_urut'       => (string)($raw['nomor_urut'] ?? $raw['tanah_nomor_urut'] ?? $raw['no_urut'] ?? ''),
                'jumlah_luas'      => (string)($raw['jumlah_luas'] ?? $raw['luas_m2'] ?? $raw['luas'] ?? ''),
                'status_hak_tanah' => $raw['status_hak_tanah'] ?? $raw['status_hak'] ?? null,
                // dukung enum single / array multi
                'penggunaan_tanah' => isset($raw['penggunaan_tanah'])
                                        ? (array)$raw['penggunaan_tanah']
                                        : (isset($raw['penggunaan']) ? (array)$raw['penggunaan'] : []),
                'keterangan'       => $raw['keterangan'] ?? null,
            ],
            'tanah' => [
                'nama_pemilik'     => $raw['nama_pemilik'] ?? $raw['nama_pemilik_text'] ?? $raw['warga_nama'] ?? null,
                'nomor_urut'       => (string)($raw['nomor_urut'] ?? $raw['no_urut'] ?? ''),
                'jumlah_luas'      => (string)($raw['jumlah_luas'] ?? $raw['jumlah_m2'] ?? $raw['luas_total'] ?? ''),
                'status_hak_tanah' => $raw['status_hak_tanah'] ?? null, // biasanya per-bidang; boleh kosong
                'penggunaan_tanah' => (array)($raw['penggunaan_tanah'] ?? []),
                'keterangan'       => $raw['keterangan'] ?? null,
            ],
            'warga' => [
                'nama_pemilik'     => $raw['nama_pemilik'] ?? $raw['nama_lengkap'] ?? $raw['nama'] ?? null,
                'nomor_urut'       => null,
                'jumlah_luas'      => null,
                'status_hak_tanah' => null,
                'penggunaan_tanah' => [],
                'keterangan'       => $raw['keterangan'] ?? null,
            ],
            default => $raw, // fallback: kirim apa adanya
        };
    }

    // Mapper snapshot current (kalau payload null) → FE fields
    private function mapBidangToFE(\App\Models\Bidang $b): array
    {
        $owner = optional(optional($b->tanah)->pemilik);
        return [
            'nama_pemilik'     => $owner->nama_lengkap ?? null,
            'nomor_urut'       => (string) optional($b->tanah)->nomor_urut,
            'jumlah_luas'      => (string) $b->luas_m2,
            'status_hak_tanah' => $b->status_hak,
            'penggunaan_tanah' => is_string($b->penggunaan) && $b->penggunaan !== '' ? [$b->penggunaan] : (array)$b->penggunaan,
            'keterangan'       => $b->keterangan,
        ];
    }

    private function mapTanahToFE(\App\Models\Tanah $t): array
    {
        $owner = optional($t->pemilik);
        return [
            'nama_pemilik'     => $owner->nama_lengkap ?? null,
            'nomor_urut'       => (string) $t->nomor_urut,
            'jumlah_luas'      => (string) $t->jumlah_m2,
            'status_hak_tanah' => null,
            'penggunaan_tanah' => [],
            'keterangan'       => $t->keterangan,
        ];
    }

    private function mapWargaToFE(\App\Models\Warga $w): array
    {
        return [
            'nama_pemilik'     => $w->nama_lengkap,
            'nomor_urut'       => null,
            'jumlah_luas'      => null,
            'status_hak_tanah' => null,
            'penggunaan_tanah' => [],
            'keterangan'       => $w->keterangan,
        ];
    }

}
