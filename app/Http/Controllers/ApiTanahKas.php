<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiTanahKas extends Controller
{
    /**
     * GET /api/v1/external/bidang-simple
     * Optional query:
     * - per_page=1..200 (default 50)
     * - status_hak=HM,HGB,...
     * - penggunaan=PERUMAHAN,SAWAH,...
     * - tanah_id=ID
     */
    public function index(Request $r)
    {
        $perPage = max(1, min((int)$r->input('per_page', 50), 200));

        $q = DB::table('bidang as b')
            ->join('tanah as t', 't.id', '=', 'b.tanah_id')
            ->leftJoin('warga as w', 'w.id', '=', 't.warga_id')
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
                w.alamat_lengkap
            ");

        // Filter sederhana (opsional)
        if ($r->filled('status_hak')) {
            $q->where('b.status_hak', strtoupper($r->input('status_hak')));
        }
        if ($r->filled('penggunaan')) {
            $q->where('b.penggunaan', strtoupper($r->input('penggunaan')));
        }
        if ($r->filled('tanah_id')) {
            $q->where('b.tanah_id', (int)$r->input('tanah_id'));
        }

        // Urut terbaru
        $q->orderByDesc('b.updated_at')->orderByDesc('b.id');

        $p = $q->paginate($perPage);

        $rows = collect($p->items())->map(function ($row) {
            return [
                'id'           => (int) $row->id,
                'tanah_id'     => (int) $row->tanah_id,
                'tanah'        => [
                    'id'          => (int) $row->tanah_id,
                    'nomor_urut'  => $row->tanah_nomor_urut,
                ],
                'luas_m2'      => (float) $row->luas_m2,
                'status_hak'   => $row->status_hak,
                'penggunaan'   => $row->penggunaan,
                'keterangan'   => $row->keterangan,
                'created_at'   => optional($row->created_at)->toISOString() ?? (string) $row->created_at,
                'updated_at'   => optional($row->updated_at)->toISOString() ?? (string) $row->updated_at,

                // Identitas pemilik (warga)
                'warga'        => $row->warga_id ? [
                    'id'             => (int) $row->warga_id,
                    'nama_lengkap'   => $row->nama_lengkap,
                    'nik'            => $row->nik,
                    'jenis_kelamin'  => $row->jenis_kelamin,
                    'tanggal_lahir'  => $row->tanggal_lahir ? (string) $row->tanggal_lahir : null,
                    'alamat_lengkap' => $row->alamat_lengkap,
                ] : null,
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
