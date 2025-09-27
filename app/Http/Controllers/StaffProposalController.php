<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use Illuminate\Http\Request;

class StaffProposalController extends Controller
{
    // ======== T A N A H ========

    // POST /api/proposals/tanah
    public function proposeTanahCreate(Request $r)
    {
        $data = $r->validate([
            'nomor_urut'   => 'required|string|max:64',
            'nama_pemilik' => 'required|string|max:255',
            'jumlah_m2'    => 'required|numeric|min:0',
            // kolom m2 lainnya opsional:
            'hm_m2'  => 'nullable|numeric|min:0',
            'hgb_m2' => 'nullable|numeric|min:0',
            'hp_m2'  => 'nullable|numeric|min:0',
            'hgu_m2' => 'nullable|numeric|min:0',
            'hpl_m2' => 'nullable|numeric|min:0',
            'ma_m2'  => 'nullable|numeric|min:0',
            'vi_m2'  => 'nullable|numeric|min:0',
            'tn_m2'  => 'nullable|numeric|min:0',
            'perumahan_m2'             => 'nullable|numeric|min:0',
            'perdagangan_jasa_m2'      => 'nullable|numeric|min:0',
            'perkantoran_m2'           => 'nullable|numeric|min:0',
            'industri_m2'              => 'nullable|numeric|min:0',
            'fasilitas_umum_m2'        => 'nullable|numeric|min:0',
            'sawah_m2'                 => 'nullable|numeric|min:0',
            'tegalan_m2'               => 'nullable|numeric|min:0',
            'perkebunan_m2'            => 'nullable|numeric|min:0',
            'peternakan_perikanan_m2'  => 'nullable|numeric|min:0',
            'hutan_belukar_m2'         => 'nullable|numeric|min:0',
            'hutan_lindung_m2'         => 'nullable|numeric|min:0',
            'tanah_kosong_m2'          => 'nullable|numeric|min:0',
            'lain_lain_m2'             => 'nullable|numeric|min:0',
            'mutasi_tanah'             => 'nullable|string',
            'keterangan'               => 'nullable|string',
            'geojson_boundary'         => 'nullable|array', // Feature/geometry
        ]);

        if (empty($data['warga_id']) && empty($data['pemilik'])) {
                return response()->json([
                    'message' => 'Harus pilih pemilik atau isi data pemilik baru.'
                ], 422);
        }

        $ar = ApprovalRequest::create([
            'module'       => 'tanah',
            'action'       => 'create',
            'payload'      => $data,
            'submitted_by' => $r->user()->id,
        ]);

        return response()->json([
            'message' => 'Proposal tanah dibuat, menunggu persetujuan kepala.',
            'id'      => $ar->id,
            'status'  => $ar->status,
        ], 202);


    }

    // PATCH /api/proposals/tanah/{id}
    public function proposeTanahUpdate(Request $r, $id)
    {
        $delta = $r->validate([
            'fields' => 'required|array', // kirim hanya perubahan, mis: {"jumlah_m2":1200}
        ]);

        $ar = ApprovalRequest::create([
            'module'       => 'tanah',
            'action'       => 'update',
            'target_id'    => $id,
            'payload'      => $delta['fields'],
            'submitted_by' => $r->user()->id,
        ]);

        return response()->json(['message' => 'Proposal update tanah dibuat', 'id' => $ar->id], 202);
    }

    // DELETE /api/proposals/tanah/{id}
    public function proposeTanahDelete(Request $r, $id)
    {
        $ar = ApprovalRequest::create([
            'module'       => 'tanah',
            'action'       => 'delete',
            'target_id'    => $id,
            'payload'      => ['reason' => $r->input('reason')],
            'submitted_by' => $r->user()->id,
        ]);

        return response()->json(['message' => 'Proposal hapus tanah dibuat', 'id' => $ar->id], 202);
    }

    // ======== W A R G A ========

    // POST /api/proposals/warga
    public function proposeWargaCreate(Request $r)
    {
        $data = $r->validate([
            'nama_lengkap'      => 'required|string|max:255',
            'jenis_kelamin'     => 'required|in:L,P',
            'nik'               => 'nullable|digits:16',
            'nomor_kk'          => 'nullable|string|max:32',
            'tempat_lahir'      => 'nullable|string|max:64',
            'tanggal_lahir'     => 'nullable|date',
            'agama'             => 'nullable|string|max:32',
            'pendidikan_terakhir'=> 'nullable|string|max:64',
            'pekerjaan'         => 'nullable|string|max:64',
            'status_perkawinan' => 'nullable|in:BELUM KAWIN,KAWIN,CERAI HIDUP,CERAI MATI',
            'kedudukan_keluarga'=> 'nullable|string|max:32',
            'kewarganegaraan'   => 'nullable|in:WNI,WNA',
            'alamat_lengkap'    => 'nullable|string',
            'keterangan'        => 'nullable|string',
        ]);

        $ar = ApprovalRequest::create([
            'module'       => 'warga',
            'action'       => 'create',
            'payload'      => $data,
            'submitted_by' => $r->user()->id,
        ]);

        return response()->json(['message' => 'Proposal warga dibuat', 'id' => $ar->id], 202);
    }

    // PATCH /api/proposals/warga/{id}
    public function proposeWargaUpdate(Request $r, $id)
    {
        $delta = $r->validate(['fields' => 'required|array']);

        $ar = ApprovalRequest::create([
            'module'       => 'warga',
            'action'       => 'update',
            'target_id'    => $id,
            'payload'      => $delta['fields'],
            'submitted_by' => $r->user()->id,
        ]);

        return response()->json(['message' => 'Proposal update warga dibuat', 'id' => $ar->id], 202);
    }

    // DELETE /api/proposals/warga/{id}
    public function proposeWargaDelete(Request $r, $id)
    {
        $ar = ApprovalRequest::create([
            'module'       => 'warga',
            'action'       => 'delete',
            'target_id'    => $id,
            'payload'      => ['reason' => $r->input('reason')],
            'submitted_by' => $r->user()->id,
        ]);

        return response()->json(['message' => 'Proposal hapus warga dibuat', 'id' => $ar->id], 202);
    }

    // GET /api/proposals/my
    public function myProposals(Request $r)
    {
        return response()->json(
            ApprovalRequest::where('submitted_by', $r->user()->id)->latest()->paginate(20)
        );
    }
}
