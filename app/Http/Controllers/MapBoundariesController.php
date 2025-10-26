<?php

namespace App\Http\Controllers;

use App\Models\Bidang;
use Illuminate\Http\Request;

class MapBoundariesController extends Controller
{
    /**
     * GET /api/map/bidang.geojson
     *
     * Query (opsional):
     * - status_hak=HM,HGB,HP,HGU,HPL,MA,VI,TN
     * - penggunaan=SAWAH,PERKANTORAN,PERUMAHAN, ... (daftar enum di DB)
     * - tanah_id=ID
     * - warga_id=ID            (filter melalui relasi Tanah)
     * - bbox=minLng,minLat,maxLng,maxLat (filter kasar via centroid_*)
     * - limit=2000 (maks baris dikirim agar respons tetap ringan)
     * - include_props=true|false (default true; false untuk properti minimal)
     *
     * Response: GeoJSON FeatureCollection
     */
    public function bidang(Request $r)
    {
        // --- Parsers ---
        $allowedHak = ['HM','HGB','HP','HGU','HPL','MA','VI','TN'];
        $allowedGuna = [
            'PERUMAHAN','PERDAGANGAN_JASA','PERKANTORAN','INDUSTRI','FASILITAS_UMUM',
            'SAWAH','TEGALAN','PERKEBUNAN','PETERNAKAN_PERIKANAN',
            'HUTAN_BELUKAR','HUTAN_LINDUNG','MUTASI_TANAH','TANAH_KOSONG','LAIN_LAIN'
        ];

        $parseCsv = function (?string $csv, array $allowed) {
            if (!$csv) return [];
            $vals = collect(explode(',', $csv))
                ->map(fn($v) => strtoupper(trim($v)))
                ->filter()
                ->unique()
                ->values()
                ->all();
            // jaga hanya enum yang sah
            return array_values(array_intersect($vals, $allowed));
        };

        $statusHak = $parseCsv($r->query('status_hak'), $allowedHak);
        $penggunaan = $parseCsv($r->query('penggunaan'), $allowedGuna);

        $limit = (int) $r->query('limit', 2000);
        $limit = max(1, min($limit, 10000)); // pagar atas

        $includeProps = $r->boolean('include_props', true);

        // --- Query dasar ---
        $q = Bidang::query()
            ->whereNull('bidang.deleted_at')
            ->whereNotNull('geojson_id')
            ->with(['geojson:id,nama,feature_json,srid,centroid_lng,centroid_lat,properties'])
            ->select([
                'bidang.id','bidang.tanah_id','bidang.geojson_id',
                'bidang.luas_m2','bidang.status_hak','bidang.penggunaan',
                'bidang.keterangan','bidang.updated_at'
            ]);

        if ($statusHak)   $q->whereIn('bidang.status_hak', $statusHak);
        if ($penggunaan)  $q->whereIn('bidang.penggunaan', $penggunaan);
        if ($r->filled('tanah_id')) $q->where('bidang.tanah_id', (int) $r->query('tanah_id'));

        // filter by warga_id melalui relasi Tanah
        if ($r->filled('warga_id')) {
            $wid = (int) $r->query('warga_id');
            $q->whereHas('tanah', fn($t) => $t->where('warga_id', $wid));
        }

        // bbox via centroid (kasar, tapi hemat karena tanpa fungsi GIS)
        if ($r->filled('bbox')) {
            $parts = array_map('trim', explode(',', $r->query('bbox')));
            if (count($parts) === 4) {
                [$minLng, $minLat, $maxLng, $maxLat] = array_map('floatval', $parts);
                $q->whereHas('geojson', function ($g) use ($minLng, $maxLng, $minLat, $maxLat) {
                    $g->whereBetween('centroid_lng', [$minLng, $maxLng])
                      ->whereBetween('centroid_lat', [$minLat, $maxLat]);
                });
            }
        }

        $q->orderByDesc('bidang.updated_at')->limit($limit);

        $rows = $q->get();

        // --- Susun FeatureCollection ---
        $features = [];
        foreach ($rows as $b) {
            $g = $b->geojson;                 // bisa null kalau orphan, tapi kita sudah whereNotNull
            if (!$g) continue;

            $feat = json_decode($g->feature_json, true);
            if (!$feat) continue;

            // Jika yang tersimpan Geometry, bungkus jadi Feature
            if (($feat['type'] ?? null) !== 'Feature') {
                $feat = [
                    'type' => 'Feature',
                    'properties' => (object)[],
                    'geometry' => $feat,
                ];
            }

            // safety: hanya Polygon/Multipolygon yang diizinkan untuk overlay ini
            $geomType = $feat['geometry']['type'] ?? null;
            if (!in_array($geomType, ['Polygon','MultiPolygon'])) {
                continue;
            }

            // properties gabungan (dari geojson + bidang)
            $props = is_array($feat['properties'] ?? null) ? $feat['properties'] : [];
            $baseProps = [
                'bidang_id'   => (int) $b->id,
                'tanah_id'    => (int) $b->tanah_id,
                'geojson_id'  => (int) $b->geojson_id,
                'luas_m2'     => (float) $b->luas_m2,
                'status_hak'  => $b->status_hak,
                'penggunaan'  => $b->penggunaan,
                'keterangan'  => $b->keterangan,
                'srid'        => (int) ($g->srid ?? 4326),
                'nama'        => $g->nama,
                'centroid'    => [$g->centroid_lng, $g->centroid_lat],
                'updated_at'  => optional($b->updated_at)->toIso8601String(),
            ];

            if (!$includeProps) {
                // minimalis untuk performa
                $baseProps = [
                    'bidang_id'  => (int) $b->id,
                    'status_hak' => $b->status_hak,
                    'penggunaan' => $b->penggunaan,
                    'luas_m2'    => (float) $b->luas_m2,
                ];
            }

            $feat['properties'] = array_merge($props, $baseProps);
            $features[] = $feat;
        }

        $payload = [
            'type'     => 'FeatureCollection',
            'features' => $features,
            'meta'     => [
                'count'       => count($features),
                'filters'     => [
                    'status_hak' => $statusHak ?: null,
                    'penggunaan' => $penggunaan ?: null,
                    'tanah_id'   => $r->query('tanah_id'),
                    'warga_id'   => $r->query('warga_id'),
                    'bbox'       => $r->query('bbox'),
                ],
                'limit'       => $limit,
                'include_props' => $includeProps,
            ],
        ];

        return response()
            ->json($payload)
            ->header('Content-Type', 'application/geo+json; charset=utf-8');
    }
}
