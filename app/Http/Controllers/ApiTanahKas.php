<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiTanahKas extends Controller
{
    /**
     * GET /api/v1/external/bidang-simple
     *
     * Query (opsional):
     * - per_page=1..200 (default 50)
     * - status_hak=HM,HGB,HP,HGU,HPL,MA,VI,TN
     * - penggunaan=PERUMAHAN,SAWAH,PERDAGANGAN_JASA,...
     * - tanah_id=ID
     * - has_geo=0|1           (default 0; 1 = hanya yang punya geojson)
     * - srid=4326             (filter berdasarkan SRID geojson)
     * - include_geometry=0|1  (default 0; 1 = sertakan feature GeoJSON penuh)
     */
    public function index(Request $r)
    {
        $perPage = max(1, min((int) $r->input('per_page', 50), 200));
        $includeGeom = $r->boolean('include_geometry', false);

        $q = DB::table('bidang as b')
            ->join('tanah as t', 't.id', '=', 'b.tanah_id')
            ->leftJoin('warga as w', 'w.id', '=', 't.warga_id')
            ->leftJoin('geojson as g', 'g.id', '=', 'b.geojson_id')
            ->whereNull('b.deleted_at')
            ->selectRaw("
                b.id,
                b.tanah_id,
                b.luas_m2,
                b.status_hak,
                b.penggunaan,
                b.keterangan,
                b.created_at,
                b.updated_at,

                t.nomor_urut as tanah_nomor_urut,

                w.id as warga_id,
                w.nama_lengkap,
                w.nik,
                w.jenis_kelamin,
                w.tanggal_lahir,
                w.alamat_lengkap,

                g.id as geojson_id,
                g.srid as geojson_srid,
                g.centroid_lng,
                g.centroid_lat,
                g.feature_json
            ");

        // ---- Filters
        if ($r->filled('status_hak')) {
            $q->where('b.status_hak', strtoupper($r->input('status_hak')));
        }
        if ($r->filled('penggunaan')) {
            $q->where('b.penggunaan', strtoupper($r->input('penggunaan')));
        }
        if ($r->filled('tanah_id')) {
            $q->where('b.tanah_id', (int) $r->input('tanah_id'));
        }
        if ($r->boolean('has_geo', false)) {
            $q->whereNotNull('b.geojson_id');
        }
        if ($r->filled('srid')) {
            $q->where('g.srid', (int) $r->input('srid'));
        }

        // Urut terbaru
        $q->orderByDesc('b.updated_at')->orderByDesc('b.id');

        $p = $q->paginate($perPage);

        $rows = collect($p->items())->map(function ($row) use ($includeGeom) {
            // Siapkan blok geometry (centroid + opsional feature)
            $geometry = null;
            if (!is_null($row->geojson_id)) {
                $geometry = [
                    'geojson_id' => (int) $row->geojson_id,
                    'srid'       => $row->geojson_srid !== null ? (int) $row->geojson_srid : null,
                    'centroid'   => ($row->centroid_lng !== null && $row->centroid_lat !== null)
                        ? ['lng' => (float) $row->centroid_lng, 'lat' => (float) $row->centroid_lat]
                        : null,
                ];

                if ($includeGeom && $row->feature_json) {
                    // Kembalikan GeoJSON apa adanya (Feature/Polygon/FeatureCollection)
                    $decoded = json_decode($row->feature_json, true);
                    $geometry['feature'] = $decoded ?: null; // null jika JSON tidak valid
                }
            }

            return [
                'id'         => (int) $row->id,
                'tanah_id'   => (int) $row->tanah_id,
                'tanah'      => [
                    'id'         => (int) $row->tanah_id,
                    'nomor_urut' => $row->tanah_nomor_urut,
                ],
                'luas_m2'    => (float) $row->luas_m2,
                'status_hak' => $row->status_hak,
                'penggunaan' => $row->penggunaan,
                'keterangan' => $row->keterangan,
                'created_at' => (string) $row->created_at,
                'updated_at' => (string) $row->updated_at,

                'warga'      => $row->warga_id ? [
                    'id'             => (int) $row->warga_id,
                    'nama_lengkap'   => $row->nama_lengkap,
                    'nik'            => $row->nik,
                    'jenis_kelamin'  => $row->jenis_kelamin,
                    'tanggal_lahir'  => $row->tanggal_lahir ? (string) $row->tanggal_lahir : null,
                    'alamat_lengkap' => $row->alamat_lengkap,
                ] : null,

                'geometry'   => $geometry,
            ];
        });

        return response()->json([
            'pagination' => [
                'current_page' => $p->currentPage(),
                'per_page'     => $p->perPage(),
                'total'        => $p->total(),
                'last_page'    => $p->lastPage(),
                'from'         => $p->firstItem(),
                'to'           => $p->lastItem(),
            ],
            'data' => $rows,
        ]);
    }
}
