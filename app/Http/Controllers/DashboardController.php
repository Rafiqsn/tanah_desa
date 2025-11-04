<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Warga;
use App\Models\Tanah;
use App\Models\Bidang;
use App\Models\ApprovalRequest;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * GET /api/kepala/dashboard
     *
     * Query opsional:
     * - month=1..12 & year=YYYY
     * - date_from=YYYY-MM-DD & date_to=YYYY-MM-DD
     * - activity_limit (default 10)
     * - include_payload=0|1
     */
    public function overview(Request $r)
    {
        // ====== Periode untuk CHART & ACTIVITY (card dihitung global) ======
        [$start, $end] = $this->resolveDateRange($r);

        // ====== KARTU METRIK (global, tidak terikat periode) ======
        $totalWarga      = (int) Warga::count();
        $totalTanah      = (int) Tanah::count();
        $pendingApproval = (int) ApprovalRequest::where('status','pending')->count();
        $totalLuasTerpakaiM2 = (float) Bidang::whereNull('deleted_at')->sum('luas_m2');

        $cards = [
            'total_warga'            => $totalWarga,
            'total_tanah'            => $totalTanah,
            // ganti "sertifikat_aktif" -> total luas yang digunakan
            'total_luas_terpakai_m2' => $totalLuasTerpakaiM2,
            'pending_approval'       => $pendingApproval,
        ];

        // ====== CHART: Penggunaan Tanah (terikat periode) ======
        $penggunaanQ = Bidang::query()
            ->whereNull('deleted_at')
            ->select(
                'penggunaan',
                DB::raw('COUNT(*) as total'),
                DB::raw('COALESCE(SUM(luas_m2),0) as sum_luas_m2')
            )
            ->groupBy('penggunaan');

        if ($start) $penggunaanQ->whereDate('created_at', '>=', $start);
        if ($end)   $penggunaanQ->whereDate('created_at', '<=', $end);

        $penggunaan = $penggunaanQ->orderBy('penggunaan')->get()
            ->map(fn($row) => [
                'label'       => $row->penggunaan,
                'count'       => (int) $row->total,
                'sum_luas_m2' => (float) $row->sum_luas_m2,
            ]);

        // ====== CHART: Status Sertifikat (terikat periode) ======
        $statusQ = Bidang::query()
            ->whereNull('deleted_at')
            ->select('status_hak', DB::raw('COUNT(*) as total'))
            ->groupBy('status_hak');

        if ($start) $statusQ->whereDate('created_at', '>=', $start);
        if ($end)   $statusQ->whereDate('created_at', '<=', $end);

        $statusSertifikat = $statusQ->orderBy('status_hak')->get()
            ->map(fn($row) => [
                'label' => $row->status_hak,
                'count' => (int) $row->total,
            ]);

        // ====== RINGKASAN TOTAL TANAH (struktur seperti contohmu) ======
        $sumQ = DB::table('bidang')->whereNull('deleted_at');
        if ($start) $sumQ->whereDate('created_at', '>=', $start);
        if ($end)   $sumQ->whereDate('created_at', '<=', $end);

        $s = $sumQ->selectRaw("
            COUNT(*) as bidang,

            -- Status Hak (bersertifikat)
            SUM(CASE WHEN status_hak='HM'  THEN COALESCE(luas_m2,0) ELSE 0 END) as hm,
            SUM(CASE WHEN status_hak='HGB' THEN COALESCE(luas_m2,0) ELSE 0 END) as hgb,
            SUM(CASE WHEN status_hak='HP'  THEN COALESCE(luas_m2,0) ELSE 0 END) as hp,
            SUM(CASE WHEN status_hak='HGU' THEN COALESCE(luas_m2,0) ELSE 0 END) as hgu,
            SUM(CASE WHEN status_hak='HPL' THEN COALESCE(luas_m2,0) ELSE 0 END) as hpl,

            -- Status Hak (belum bersertifikat)
            SUM(CASE WHEN status_hak='MA'  THEN COALESCE(luas_m2,0) ELSE 0 END) as ma,
            SUM(CASE WHEN status_hak='VI'  THEN COALESCE(luas_m2,0) ELSE 0 END) as vi,
            SUM(CASE WHEN status_hak='TN'  THEN COALESCE(luas_m2,0) ELSE 0 END) as tn,

            -- Penggunaan: Non Pertanian
            SUM(CASE WHEN penggunaan='PERUMAHAN'         THEN COALESCE(luas_m2,0) ELSE 0 END) as perumahan,
            SUM(CASE WHEN penggunaan='PERDAGANGAN_JASA'  THEN COALESCE(luas_m2,0) ELSE 0 END) as perdagangan_jasa,
            SUM(CASE WHEN penggunaan='PERKANTORAN'       THEN COALESCE(luas_m2,0) ELSE 0 END) as perkantoran,
            SUM(CASE WHEN penggunaan='INDUSTRI'          THEN COALESCE(luas_m2,0) ELSE 0 END) as industri,
            SUM(CASE WHEN penggunaan='FASILITAS_UMUM'    THEN COALESCE(luas_m2,0) ELSE 0 END) as fasilitas_umum,

            -- Penggunaan: Pertanian
            SUM(CASE WHEN penggunaan='SAWAH'                 THEN COALESCE(luas_m2,0) ELSE 0 END) as sawah,
            SUM(CASE WHEN penggunaan='TEGALAN'               THEN COALESCE(luas_m2,0) ELSE 0 END) as tegalan,
            SUM(CASE WHEN penggunaan='PERKEBUNAN'            THEN COALESCE(luas_m2,0) ELSE 0 END) as perkebunan,
            SUM(CASE WHEN penggunaan='PETERNAKAN_PERIKANAN'  THEN COALESCE(luas_m2,0) ELSE 0 END) as peternakan_perikanan,
            SUM(CASE WHEN penggunaan='HUTAN_BELUKAR'         THEN COALESCE(luas_m2,0) ELSE 0 END) as hutan_belukar,
            SUM(CASE WHEN penggunaan='HUTAN_LINDUNG'         THEN COALESCE(luas_m2,0) ELSE 0 END) as hutan_lindung,
            SUM(CASE WHEN penggunaan='MUTASI_TANAH'          THEN COALESCE(luas_m2,0) ELSE 0 END) as mutasi_tanah,
            SUM(CASE WHEN penggunaan='TANAH_KOSONG'          THEN COALESCE(luas_m2,0) ELSE 0 END) as tanah_kosong,
            SUM(CASE WHEN penggunaan='LAIN_LAIN'             THEN COALESCE(luas_m2,0) ELSE 0 END) as lain_lain
        ")->first();

        $bersertifikat = (float)$s->hm + (float)$s->hgb + (float)$s->hp + (float)$s->hgu + (float)$s->hpl;
        $belum         = (float)$s->ma + (float)$s->vi + (float)$s->tn;
        $totalHak      = $bersertifikat + $belum;

        $totalNon      = (float)$s->perumahan + (float)$s->perdagangan_jasa + (float)$s->perkantoran
                       + (float)$s->industri + (float)$s->fasilitas_umum;

        $totalPert     = (float)$s->sawah + (float)$s->tegalan + (float)$s->perkebunan
                       + (float)$s->peternakan_perikanan + (float)$s->hutan_belukar + (float)$s->hutan_lindung
                       + (float)$s->mutasi_tanah + (float)$s->tanah_kosong + (float)$s->lain_lain;

        $totalTanahSummary = [
            'meta' => [
                'bidang' => (int)$s->bidang,
                'period' => [
                    'from' => $start?->toDateString(),
                    'to'   => $end?->toDateString(),
                ],
            ],
            'ringkasan' => [
                'total_status_hak_m2'  => $totalHak,
                'bersertifikat_m2'     => $bersertifikat,
                'belum_sertifikat_m2'  => $belum,
                'non_pertanian_m2'     => $totalNon,
                'pertanian_m2'         => $totalPert,
            ],
            'rincian' => [
                'status_hak' => [
                    'bersertifikat' => [
                        'hm'  => (float)$s->hm,
                        'hgb' => (float)$s->hgb,
                        'hp'  => (float)$s->hp,
                        'hgu' => (float)$s->hgu,
                        'hpl' => (float)$s->hpl,
                    ],
                    'belum_bersertifikat' => [
                        'ma' => (float)$s->ma,
                        'vi' => (float)$s->vi,
                        'tn' => (float)$s->tn,
                    ],
                ],
                'penggunaan' => [
                    'non_pertanian' => [
                        'perumahan'         => (float)$s->perumahan,
                        'perdagangan_jasa'  => (float)$s->perdagangan_jasa,
                        'perkantoran'       => (float)$s->perkantoran,
                        'industri'          => (float)$s->industri,
                        'fasilitas_umum'    => (float)$s->fasilitas_umum,
                    ],
                    'pertanian' => [
                        'sawah'                => (float)$s->sawah,
                        'tegalan'              => (float)$s->tegalan,
                        'perkebunan'           => (float)$s->perkebunan,
                        'peternakan_perikanan' => (float)$s->peternakan_perikanan,
                        'hutan_belukar'        => (float)$s->hutan_belukar,
                        'hutan_lindung'        => (float)$s->hutan_lindung,
                        'mutasi_tanah'         => (float)$s->mutasi_tanah,
                        'tanah_kosong'         => (float)$s->tanah_kosong,
                        'lain_lain'            => (float)$s->lain_lain,
                    ],
                ],
            ],
        ];

        // ====== AKTIVITAS TERBARU (terikat periode) ======
        $actLimit = max(1, min((int)$r->input('activity_limit', 10), 100));
        $includePayload = $r->boolean('include_payload', false);

        $activityQ = ApprovalRequest::query()
            ->with(['submitter:id,name,email', 'reviewer:id,name,email'])
            ->orderByDesc('reviewed_at')->orderByDesc('id');

        if ($start) $activityQ->whereDate('reviewed_at', '>=', $start);
        if ($end)   $activityQ->whereDate('reviewed_at', '<=', $end);

        $labelMap = ['create'=>'Tambah','update'=>'Edit','delete'=>'Hapus'];

        $activity = $activityQ->limit($actLimit)->get()
            ->map(function (ApprovalRequest $a) use ($labelMap, $includePayload) {
                return [
                    'id'             => $a->id,
                    'module'         => $a->module,
                    'action'         => $a->action,
                    'jenis_perubahan'=> $labelMap[$a->action] ?? strtoupper($a->action),
                    'status'         => $a->status,
                    'target_id'      => $a->target_id,
                    'review_note'    => $a->review_note,
                    'submitted_at'   => optional($a->created_at)?->toIso8601String(),
                    'reviewed_at'    => optional($a->reviewed_at)?->toIso8601String(),
                    'submitted_by'   => [
                        'id'    => $a->submitted_by,
                        'name'  => optional($a->submitter)->name,
                        'email' => optional($a->submitter)->email,
                    ],
                    'reviewed_by'    => [
                        'id'    => $a->reviewed_by,
                        'name'  => optional($a->reviewer)->name,
                        'email' => optional($a->reviewer)->email,
                    ],
                    'payload'        => $includePayload ? $a->payload : null,
                ];
            });

        // ====== RESPONSE ======
        return response()->json([
            'filters' => [
                'month'     => $r->input('month'),
                'year'      => $r->input('year'),
                'date_from' => $start?->toDateString(),
                'date_to'   => $end?->toDateString(),
            ],
            'cards'        => $cards,
            'charts'       => [
                'penggunaan'        => $penggunaan,
                'status_sertifikat' => $statusSertifikat,
            ],
            // <— FE bisa fetch bagian ini untuk angka total luas & rincian
            'total_tanah'  => $totalTanahSummary,
            'activity'     => $activity,
        ]);
    }

    /** Resolve rentang tanggal dari query (month/year atau date_from/date_to). */
    private function resolveDateRange(Request $r): array
    {
        if ($r->filled('month') && $r->filled('year')) {
            $month = max(1, min(12, (int)$r->input('month')));
            $year  = (int) $r->input('year');
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $end   = (clone $start)->endOfMonth()->endOfDay();
            return [$start, $end];
        }
        $start = $r->filled('date_from') ? Carbon::parse($r->input('date_from'))->startOfDay() : null;
        $end   = $r->filled('date_to')   ? Carbon::parse($r->input('date_to'))->endOfDay()   : null;
        return [$start, $end];
    }
}
