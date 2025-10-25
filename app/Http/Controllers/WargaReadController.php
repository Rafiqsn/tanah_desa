<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Warga;
use Illuminate\Http\Request;

class WargaReadController extends Controller
{
    /**
     * GET /api/warga?search=&has_tanah=1&per_page=20
     * - Cari: nama_lengkap / NIK
     * - Filter: has_tanah=1 untuk hanya warga yang punya entri tanah
     * - Ringkasan: tanah_count
     */
    public function index(Request $r)
    {
        $perPage = (int) $r->input('per_page', 20);

        $q = Warga::query()
            ->withCount('tanah')                 // ringkasan jumlah tanah per warga
            ->orderBy('nama_lengkap', 'asc');

        if ($s = $r->input('search')) {
            $q->where(function ($w) use ($s) {
                $w->where('nama_lengkap', 'like', "%{$s}%")
                  ->orWhere('nik', 'like', "%{$s}%");
            });
        }

        if ($r->boolean('has_tanah')) {
            $q->has('tanah');
        }

        return response()->json($q->paginate($perPage));
    }

    /**
     * GET /api/warga/{id}
     * Detail warga + daftar tanah (ringan), tiap tanah memuat ringkasan bidang.
     * (Jika perlu GeoJSON penuh, sediakan endpoint khusus agar payload tidak berat.)
     */
    public function show($id)
    {
        $row = Warga::with([
                // ambil tanah milik warga
                'tanah:id,warga_id,nomor_urut,jumlah_m2,created_at,updated_at',
                // ringkas bidang per tanah (tanpa feature_json)
                'tanah.bidang:id,tanah_id,luas_m2,status_hak,penggunaan'
            ])
            ->withCount('tanah')
            ->findOrFail($id);

        return response()->json($row);
    }
}
