<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PublicInfografisController extends Controller
{
    // GET /api/public/infografis/summary
        public function summary()
    {
        $data = Cache::remember('pub:infografis:summary', now()->addMinutes(30), function () {
            $s = DB::table('tanah')->selectRaw('
                -- Status Hak
                SUM(COALESCE(hm,0))  as hm,
                SUM(COALESCE(hgb,0)) as hgb,
                SUM(COALESCE(hp,0))  as hp,
                SUM(COALESCE(hgu,0)) as hgu,
                SUM(COALESCE(hpl,0)) as hpl,
                SUM(COALESCE(ma,0))  as ma,
                SUM(COALESCE(vi,0))  as vi,
                SUM(COALESCE(tn,0))  as tn,

                -- Non Pertanian
                SUM(COALESCE(perumahan,0))           as perumahan,
                SUM(COALESCE(perdagangan_jasa,0))    as perdagangan_jasa,
                SUM(COALESCE(perkantoran,0))         as perkantoran,
                SUM(COALESCE(industri,0))            as industri,
                SUM(COALESCE(fasilitas_umum,0))      as fasilitas_umum,

                -- Pertanian
                SUM(COALESCE(sawah,0))                as sawah,
                SUM(COALESCE(tegalan,0))              as tegalan,
                SUM(COALESCE(perkebunan,0))           as perkebunan,
                SUM(COALESCE(peternakan_perikanan,0)) as peternakan_perikanan,
                SUM(COALESCE(hutan_belukar,0))        as hutan_belukar,
                SUM(COALESCE(hutan_lindung,0))        as hutan_lindung,
                SUM(COALESCE(mutasi_tanah,0))         as mutasi_tanah,
                SUM(COALESCE(tanah_kosong,0))         as tanah_kosong,
                SUM(COALESCE(lain_lain,0))            as lain_lain,

                COUNT(*) as bidang
            ')->first();

            // Ringkasan
            $bersertifikat = (float)$s->hm + (float)$s->hgb + (float)$s->hp + (float)$s->hgu + (float)$s->hpl;
            $belum         = (float)$s->ma + (float)$s->vi + (float)$s->tn;
            $totalHak      = $bersertifikat + $belum;

            $totalNon      = (float)$s->perumahan + (float)$s->perdagangan_jasa + (float)$s->perkantoran
                        + (float)$s->industri + (float)$s->fasilitas_umum;

            $totalPert     = (float)$s->sawah + (float)$s->tegalan + (float)$s->perkebunan
                        + (float)$s->peternakan_perikanan + (float)$s->hutan_belukar + (float)$s->hutan_lindung
                        + (float)$s->mutasi_tanah + (float)$s->tanah_kosong + (float)$s->lain_lain;

            return [
                'meta' => [
                    'cached_until' => now()->addMinutes(30)->toISOString(),
                    'bidang'       => (int)$s->bidang,
                ],
                'ringkasan' => [
                    'total_status_hak_m2'   => $totalHak,
                    'bersertifikat_m2'      => $bersertifikat,
                    'belum_sertifikat_m2'   => $belum,
                    'non_pertanian_m2'      => $totalNon,
                    'pertanian_m2'          => $totalPert,
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
        });

        return response()->json($data);
    }
}
