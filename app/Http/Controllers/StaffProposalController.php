<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\File;
use App\Models\ApprovalRequest;


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

     public function proposeWargaCreate(Request $r)
    {
        $data = $r->validate([
            'nama_lengkap'        => 'required|string|max:255',
            'jenis_kelamin'       => 'required|in:L,P',
            'nik'                 => 'nullable|digits:16',
            'tempat_lahir'        => 'nullable|string|max:64',
            'tanggal_lahir'       => 'nullable|date',
            'agama'               => 'nullable|string|max:32',
            'pendidikan_terakhir' => 'nullable|string|max:64',
            'pekerjaan'           => 'nullable|string|max:64',
            'status_perkawinan'   => 'nullable|in:BELUM KAWIN,KAWIN,CERAI HIDUP,CERAI MATI',
            'kewarganegaraan'     => 'nullable|in:WNI,WNA',
            'alamat_lengkap'      => 'nullable|string',
            'keterangan'          => 'nullable|string',

            // FILE UPLOAD (tanpa base64)
            'foto_ktp'            => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);

        if ($path = $this->saveFotoKtpToPublic($r)) {
            $data['foto_ktp'] = $path; // simpan path relatif: ktp/xxx.jpg
        }

        $ar = ApprovalRequest::create([
            'module'       => 'warga',
            'action'       => 'create',
            'payload'      => $data,
            'submitted_by' => $r->user()->id,
        ]);

        return response()->json([
            'message'       => 'Proposal warga dibuat',
            'id'            => $ar->id,
            'foto_ktp_url'  => isset($data['foto_ktp']) ? url($data['foto_ktp']) : null,
        ], 202);
    }

    /**
     * PATCH /api/proposals/warga/{id}
     * Buat proposal UPDATE Warga (boleh kirim delta di level atas atau di "fields").
     * Body: multipart/form-data bila ada file.
     */
    public function proposeWargaUpdate(Request $r, $id)
    {
        $validated = $r->validate([
            // delta level atas (opsional)
            'nama_lengkap'        => 'sometimes|required|string|max:255',
            'jenis_kelamin'       => 'sometimes|in:L,P',
            'nik'                 => 'sometimes|nullable|digits:16',
            'tempat_lahir'        => 'sometimes|nullable|string|max:64',
            'tanggal_lahir'       => 'sometimes|nullable|date',
            'agama'               => 'sometimes|nullable|string|max:32',
            'pendidikan_terakhir' => 'sometimes|nullable|string|max:64',
            'pekerjaan'           => 'sometimes|nullable|string|max:64',
            'status_perkawinan'   => 'sometimes|nullable|in:BELUM KAWIN,KAWIN,CERAI HIDUP,CERAI MATI',
            'kewarganegaraan'     => 'sometimes|nullable|in:WNI,WNA',
            'alamat_lengkap'      => 'sometimes|nullable|string',
            'keterangan'          => 'sometimes|nullable|string',

            // alternatif gaya lama: fields{}
            'fields'              => 'sometimes|array',

            // file & penghapusan
            'foto_ktp'            => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'hapus_foto_ktp'      => 'nullable|boolean',
        ]);

        // satukan payload update:
        $fields = $validated['fields'] ?? [];
        foreach ([
            'nama_lengkap','jenis_kelamin','nik','tempat_lahir','tanggal_lahir','agama',
            'pendidikan_terakhir','pekerjaan','status_perkawinan','kewarganegaraan',
            'alamat_lengkap','keterangan'
        ] as $k) {
            if ($r->has($k)) $fields[$k] = $validated[$k];
        }

        if ($r->boolean('hapus_foto_ktp')) {
            $fields['foto_ktp'] = null;
        } elseif ($path = $this->saveFotoKtpToPublic($r)) {
            $fields['foto_ktp'] = $path;
        }

        if (empty($fields)) {
            return response()->json(['message' => 'Tidak ada perubahan dikirim'], 422);
        }

        $ar = ApprovalRequest::create([
            'module'       => 'warga',
            'action'       => 'update',
            'target_id'    => $id,
            'payload'      => $fields,
            'submitted_by' => $r->user()->id,
        ]);

        return response()->json([
            'message'       => 'Proposal update warga dibuat',
            'id'            => $ar->id,
            'foto_ktp_url'  => isset($fields['foto_ktp']) ? url($fields['foto_ktp']) : null,
        ], 202);
    }

    /**
     * DELETE /api/proposals/warga/{id}
     * Buat proposal DELETE Warga.
     */
    public function proposeWargaDelete(Request $r, $id)
    {
        $ar = ApprovalRequest::create([
            'module'       => 'warga',
            'action'       => 'delete',
            'target_id'    => $id,
            'payload'      => ['reason' => $r->input('reason')],
            'submitted_by' => $r->user()->id,
        ]);

        return response()->json([
            'message' => 'Proposal hapus warga dibuat',
            'id'      => $ar->id
        ], 202);
    }

    /**
     * GET /api/proposals/my
     * Daftar proposal milik user yang sedang login (paginate 20).
     */
    public function myProposals(Request $r)
    {
        $p = ApprovalRequest::where('submitted_by', $r->user()->id)
            ->latest()
            ->paginate(20);

        // tambahkan preview URL foto_ktp bila ada di payload
        $p->getCollection()->transform(function ($item) {
            $payload = $item->payload ?? [];
            $item->foto_ktp_url = isset($payload['foto_ktp']) ? url($payload['foto_ktp']) : null;
            return $item;
        });

        return response()->json($p);
    }

    /**
     * Helper: simpan foto KTP ke public/ktp dan kembalikan PATH relatif (ktp/xxx.ext).
     * Return null jika tidak ada file pada request.
     */
    private function saveFotoKtpToPublic(Request $r): ?string
    {
        if (! $r->hasFile('foto_ktp')) {
            return null;
        }

        $file = $r->file('foto_ktp');
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['foto_ktp' => ['File tidak valid.']]);
        }

        // Pastikan folder public/ktp ada
        $dir = public_path('ktp');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0775, true, true);
            // Opsional: cegah directory listing
            @file_put_contents($dir . DIRECTORY_SEPARATOR . 'index.html', '');
        }

        // Tentukan ekstensi aman
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        if (! in_array($ext, ['jpg','jpeg','png','webp'])) {
            $ext = 'jpg';
        }

        // Nama file unik
        $name = 'ktp_' . (string) Str::uuid() . '.' . $ext;

        // Pindahkan file ke public/ktp
        $file->move($dir, $name);

        // Kembalikan path relatif untuk disimpan di DB/payload
        return 'ktp/' . $name;
    }

}
