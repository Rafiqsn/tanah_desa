<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Tanah;

class ExportTanahController extends Controller
{
    /**
     * GET /api/exports/buku-tanah.pdf
     * Export PDF
     */
    public function bukuTanahPdf(Request $request)
    {
        [$rows, $filters] = $this->buildRows($request);

        $pdf = Pdf::loadView('exports.buku_tanah', [
            'filters'    => $filters,
            'rows'       => $rows,
            'printed_at' => now()->format('d M Y H:i'),
        ])->setPaper('a3', 'landscape');

        $filename = 'buku_tanah_' . now()->format('Ymd_His') . '.pdf';
        return $pdf->download($filename);
    }

    /**
     * GET /api/exports/buku-tanah.csv
     * Export CSV tanpa paket Excel apa pun
     */
    public function bukuTanahCsv(Request $request)
    {
        [$rows, $filters] = $this->buildRows($request);

        $filename = 'buku_tanah_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            "Content-Type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=\"$filename\"",
        ];

        return response()->stream(function () use ($rows) {
            $out = fopen('php://output', 'w');

            // Header CSV
            fputcsv($out, [
                'No','Nomor Urut','Nama Pemilik','Jumlah (m²)',
                'HM','HGB','HP','HGU','HPL','MA','VI','TN',
                'Perumahan','Perdagangan Jasa','Perkantoran','Industri','Fasilitas Umum',
                'Sawah','Tegalan','Perkebunan','Peternakan/Perikanan',
                'Hutan Belukar','Hutan Lindung','Mutasi Tanah','Tanah Kosong','Lain-Lain',
                'Keterangan'
            ]);

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['no'],
                    $r['nomor_urut'],
                    $r['nama'],
                    $r['jumlah_m2'],
                    $r['hm'],
                    $r['hgb'],
                    $r['hp'],
                    $r['hgu'],
                    $r['hpl'],
                    $r['ma'],
                    $r['vi'],
                    $r['tn'],
                    $r['perumahan'],
                    $r['perdagangan_jasa'],
                    $r['perkantoran'],
                    $r['industri'],
                    $r['fasilitas_umum'],
                    $r['sawah'],
                    $r['tegalan'],
                    $r['perkebunan'],
                    $r['peternakan_perikanan'],
                    $r['hutan_belukar'],
                    $r['hutan_lindung'],
                    $r['mutasi_tanah'],
                    $r['tanah_kosong'],
                    $r['lain_lain'],
                    $r['keterangan'],
                ]);
            }

            fclose($out);
        }, 200, $headers);
    }

    /**
     * 🔧 Helper: Build rows untuk PDF & CSV
     */
    protected function buildRows(Request $request): array
    {
        $tanahQ = Tanah::query()
            ->with([
                'pemilik:id,nama_lengkap',
                'bidang' => fn($q) => $q->whereNull('deleted_at'),
            ]);

        // Search
        if ($request->filled('q')) {
            $kw = '%'.$request->q.'%';
            $tanahQ->where(function ($w) use ($kw) {
                $w->where('nomor_urut', 'like', $kw)
                  ->orWhere('nama_pemilik_text', 'like', $kw)
                  ->orWhereHas('pemilik', fn($p) => $p->where('nama_lengkap', 'like', $kw));
            });
        }

        // Filter bulan/tahun berdasarkan updated_at tanah
        if ($request->filled('month') && $request->filled('year')) {
            $tanahQ->whereYear('updated_at', (int)$request->year)
                   ->whereMonth('updated_at', (int)$request->month);
        }

        $list = $tanahQ->orderBy('nomor_urut')->get();

        $rows = $list->map(function ($t, $i) {
            $sum = fn($col,$val)=> (float)$t->bidang->where($col,$val)->sum('luas_m2');

            return [
                'no' => $i+1,
                'nomor_urut' => $t->nomor_urut,
                'nama' => optional($t->pemilik)->nama_lengkap ?? $t->nama_pemilik_text,
                'jumlah_m2' => $t->bidang->sum('luas_m2'),

                'hm'  => $sum('status_hak','HM'),
                'hgb' => $sum('status_hak','HGB'),
                'hp'  => $sum('status_hak','HP'),
                'hgu' => $sum('status_hak','HGU'),
                'hpl' => $sum('status_hak','HPL'),
                'ma'  => $sum('status_hak','MA'),
                'vi'  => $sum('status_hak','VI'),
                'tn'  => $sum('status_hak','TN'),

                'perumahan'        => $sum('penggunaan','PERUMAHAN'),
                'perdagangan_jasa' => $sum('penggunaan','PERDAGANGAN_JASA'),
                'perkantoran'      => $sum('penggunaan','PERKANTORAN'),
                'industri'         => $sum('penggunaan','INDUSTRI'),
                'fasilitas_umum'   => $sum('penggunaan','FASILITAS_UMUM'),

                'sawah'                 => $sum('penggunaan','SAWAH'),
                'tegalan'               => $sum('penggunaan','TEGALAN'),
                'perkebunan'            => $sum('penggunaan','PERKEBUNAN'),
                'peternakan_perikanan'  => $sum('penggunaan','PETERNAKAN_PERIKANAN'),
                'hutan_belukar'         => $sum('penggunaan','HUTAN_BELUKAR'),
                'hutan_lindung'         => $sum('penggunaan','HUTAN_LINDUNG'),
                'mutasi_tanah'          => $sum('penggunaan','MUTASI_TANAH'),
                'tanah_kosong'          => $sum('penggunaan','TANAH_KOSONG'),
                'lain_lain'             => $sum('penggunaan','LAIN_LAIN'),

                'keterangan' => $t->keterangan,
            ];
        });

        $filters = [
            'q' => $request->q,
            'month' => $request->month,
            'year' => $request->year,
        ];

        return [$rows, $filters];
    }
}
