<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Tanah;

class ExportTanahPdfController extends Controller
{
    /**
     * GET /api/exports/buku-tanah.pdf
     *
     * Ekspor PDF tabel “Data Tanah Desa” (format buku tanah A.6)
     * Query opsional:
     * - q            : keyword pada nama pemilik / nomor_urut
     * - month, year  : filter berdasarkan updated_at TANAH pada bulan/tahun tsb
     */
    public function bukuTanahPdf(Request $request)
    {
        // Ambil semua Tanah + relasi tipis untuk perhitungan kolom
        $tanahQ = Tanah::query()
            ->with([
                // ✅ gunakan relasi yang benar: pemilik (bukan warga)
                'pemilik:id,nama_lengkap',
                'bidang' => function ($q) {
                    $q->select('id', 'tanah_id', 'luas_m2', 'status_hak', 'penggunaan', 'created_at')
                      ->whereNull('deleted_at');
                    // ❌ tidak perlu filter month/year di sini lagi
                },
            ]);

        // Pencarian: nomor_urut, nama_pemilik_text, atau nama_lengkap pemilik
        if ($request->filled('q')) {
            $kw = '%'.$request->q.'%';
            $tanahQ->where(function ($w) use ($kw) {
                $w->where('nomor_urut', 'like', $kw)
                  ->orWhere('nama_pemilik_text', 'like', $kw)
                  ->orWhereHas('pemilik', fn ($p) => $p->where('nama_lengkap', 'like', $kw));
            });
        }

        // 🔹 Filter bulan & tahun pakai updated_at TANAH
        if ($request->filled('month') && $request->filled('year')) {
            $tanahQ->whereYear('updated_at', (int) $request->year)
                   ->whereMonth('updated_at', (int) $request->month);
        }

        $list = $tanahQ->orderBy('nomor_urut')->get();

        // Bentuk baris PDF: satu baris per TANAH (owner)
        $rows = $list->map(function ($t, $idx) {
            $sum = fn ($col, $val) => (float) $t->bidang->where($col, $val)->sum('luas_m2');

            // Status hak (m²)
            $hm  = $sum('status_hak', 'HM');
            $hgb = $sum('status_hak', 'HGB');
            $hp  = $sum('status_hak', 'HP');
            $hgu = $sum('status_hak', 'HGU');
            $hpl = $sum('status_hak', 'HPL');
            $ma  = $sum('status_hak', 'MA');
            $vi  = $sum('status_hak', 'VI');
            $tn  = $sum('status_hak', 'TN');

            // Penggunaan (m²)
            $perumahan         = $sum('penggunaan', 'PERUMAHAN');
            $perdagangan_jasa  = $sum('penggunaan', 'PERDAGANGAN_JASA');
            $perkantoran       = $sum('penggunaan', 'PERKANTORAN');
            $industri          = $sum('penggunaan', 'INDUSTRI');
            $fasilitas_umum    = $sum('penggunaan', 'FASILITAS_UMUM');
            $sawah             = $sum('penggunaan', 'SAWAH');
            $tegalan           = $sum('penggunaan', 'TEGALAN');
            $perkebunan        = $sum('penggunaan', 'PERKEBUNAN');
            $peternakan        = $sum('penggunaan', 'PETERNAKAN_PERIKANAN');
            $hutan_belukar     = $sum('penggunaan', 'HUTAN_BELUKAR');
            $hutan_lindung     = $sum('penggunaan', 'HUTAN_LINDUNG');
            $mutasi_tanah      = $sum('penggunaan', 'MUTASI_TANAH');
            $tanah_kosong      = $sum('penggunaan', 'TANAH_KOSONG');
            $lain_lain         = $sum('penggunaan', 'LAIN_LAIN');

            return [
                'no'           => $idx + 1,
                'nomor_urut'   => $t->nomor_urut,
                // ✅ pakai pemilik, fallback ke nama_pemilik_text
                'nama'         => optional($t->pemilik)->nama_lengkap ?? ($t->nama_pemilik_text ?? '-'),
                'jumlah_m2'    => (float) ($t->bidang->sum('luas_m2') ?? 0),

                // status hak
                'hm' => $hm, 'hgb' => $hgb, 'hp' => $hp, 'hgu' => $hgu, 'hpl' => $hpl,
                'ma' => $ma, 'vi' => $vi, 'tn' => $tn,

                // penggunaan non-pertanian
                'perumahan' => $perumahan, 'perdagangan_jasa' => $perdagangan_jasa,
                'perkantoran' => $perkantoran, 'industri' => $industri, 'fasilitas_umum' => $fasilitas_umum,

                // penggunaan pertanian
                'sawah' => $sawah, 'tegalan' => $tegalan, 'perkebunan' => $perkebunan,
                'peternakan_perikanan' => $peternakan, 'hutan_belukar' => $hutan_belukar,
                'hutan_lindung' => $hutan_lindung, 'mutasi_tanah' => $mutasi_tanah,
                'tanah_kosong' => $tanah_kosong, 'lain_lain' => $lain_lain,

                'keterangan'  => $t->keterangan,
            ];
        });

        $filters = [
            'q'     => $request->input('q'),
            'month' => $request->input('month'),
            'year'  => $request->input('year'),
        ];

        // Render PDF
        $pdf = Pdf::loadView('exports.buku_tanah', [
            'filters'    => $filters,
            'rows'       => $rows,
            'printed_at' => now()->format('d M Y H:i'),
        ])->setPaper('a3', 'landscape');

        $filename = 'buku_tanah_'.now()->format('Ymd_His').'.pdf';
        return $pdf->download($filename);
    }
}
