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
            // Agregasi di tabel BIDANG (exclude soft-deleted)
            $s = DB::table('bidang')->whereNull('deleted_at')->selectRaw("
                COUNT(*) as bidang,

                -- Status Hak (per bidang hanya 1 kategori)
                SUM(CASE WHEN status_hak='HM'  THEN COALESCE(luas_m2,0) ELSE 0 END) as hm,
                SUM(CASE WHEN status_hak='HGB' THEN COALESCE(luas_m2,0) ELSE 0 END) as hgb,
                SUM(CASE WHEN status_hak='HP'  THEN COALESCE(luas_m2,0) ELSE 0 END) as hp,
                SUM(CASE WHEN status_hak='HGU' THEN COALESCE(luas_m2,0) ELSE 0 END) as hgu,
                SUM(CASE WHEN status_hak='HPL' THEN COALESCE(luas_m2,0) ELSE 0 END) as hpl,
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

            // Hitung ringkasan
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
                    'total_status_hak_m2'  => $totalHak,       // total dari kategori status hak
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
        });

        return response()->json($data);
    }
}
