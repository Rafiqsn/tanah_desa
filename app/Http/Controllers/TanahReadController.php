<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tanah;
use Illuminate\Http\Request;

class TanahReadController extends Controller
{
    /**
     * GET /api/tanah?search=&status_hak=&penggunaan=&per_page=
     * - Pencarian: nomor_urut, nama_lengkap (warga), NIK
     * - Filter: status_hak/penggunaan via relasi bidang
     * - Ringkasan: bidang_count & total_luas_m2
     */
    public function index(Request $r)
    {
        $perPage = (int) $r->input('per_page', 20);

        $q = Tanah::query()
            ->with(['pemilik:id,nama_lengkap,nik'])                  // ambil nama pemilik dari tabel warga
            ->withCount('bidang')                                    // jumlah bidang per tanah
            ->withSum('bidang as total_luas_m2', 'luas_m2')          // total luas semua bidang
            ->orderByDesc('id');

        if ($s = $r->input('search')) {
            $q->where(function ($w) use ($s) {
                $w->where('nomor_urut', 'like', "%{$s}%")
                  ->orWhereHas('pemilik', function ($p) use ($s) {
                      $p->where('nama_lengkap', 'like', "%{$s}%")
                        ->orWhere('nik', 'like', "%{$s}%");
                  });
            });
        }

        if ($status = $r->input('status_hak')) {
            $q->whereHas('bidang', fn($b) => $b->where('status_hak', strtoupper($status)));
        }
        if ($guna = $r->input('penggunaan')) {
            $q->whereHas('bidang', fn($b) => $b->where('penggunaan', strtoupper($guna)));
        }

        $page = $q->paginate($perPage);

        // (opsional) flatten nama pemilik agar mudah dipakai FE
        $page->getCollection()->transform(function ($row) {
            $row->pemilik_nama = optional($row->pemilik)->nama_lengkap;
            return $row;
        });

        return response()->json($page);
    }

    /**
     * GET /api/tanah/{id}
     * Detail tanah + daftar bidang (tanpa feature_json agar payload ringan)
     */
    public function show($id)
    {
        $row = Tanah::with([
                'pemilik:id,nama_lengkap,nik,alamat_lengkap',
                'bidang:id,tanah_id,geojson_id,luas_m2,status_hak,penggunaan,keterangan',
                'bidang.geojson:id,nama' // jika butuh feature_json, buat endpoint khusus
            ])
            ->withSum('bidang as total_luas_m2', 'luas_m2')
            ->withCount('bidang')
            ->findOrFail($id);

        // (opsional) flatten
        $row->pemilik_nama = optional($row->pemilik)->nama_lengkap;

        return response()->json($row);
    }
}
