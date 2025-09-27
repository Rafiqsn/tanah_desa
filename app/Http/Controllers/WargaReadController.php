<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Warga;
use Illuminate\Http\Request;

class WargaReadController extends Controller
{
    // GET /api/warga?search=
    public function index(Request $r)
    {
        $q = Warga::query()->orderBy('nama_lengkap');

        if ($s = $r->input('search')) {
            $q->where(function ($w) use ($s) {
                $w->where('nama_lengkap', 'like', "%{$s}%")
                  ->orWhere('nik', 'like', "%{$s}%");
            });
        }

        return response()->json($q->paginate(20));
    }

    // GET /api/warga/{id}
    public function show($id)
    {
        return response()->json(Warga::findOrFail($id));
    }
}
