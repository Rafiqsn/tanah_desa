<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tanah;
use Illuminate\Http\Request;

class TanahReadController extends Controller
{
    // GET /api/tanah?search=
    public function index(Request $r)
    {
        $q = Tanah::query()->orderByDesc('id');

        if ($s = $r->input('search')) {
            $q->where(function ($w) use ($s) {
                $w->where('nomor_urut', 'like', "%{$s}%")
                  ->orWhere('nama_pemilik', 'like', "%{$s}%");
            });
        }

        // filter opsional (periode, wilayah) kalau ada kolomnya
        if ($r->filled('periode')) $q->where('periode', $r->input('periode'));
        if ($r->filled('wilayah')) $q->where('wilayah', $r->input('wilayah'));

        return response()->json($q->paginate(20));
    }

    // GET /api/tanah/{id}
    public function show($id)
    {
        return response()->json(Tanah::findOrFail($id));
    }
}
