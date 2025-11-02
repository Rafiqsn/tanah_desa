<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\File;
use App\Models\ApprovalRequest;
use App\Models\Tanah;
use App\Models\Bidang;
use App\Models\Warga;

class StaffProposalController extends Controller
{
        private function buildPemilikPreviewFromTanahId(int $tanahId): array
    {
        $t = \App\Models\Tanah::with('pemilik:id,nama_lengkap,nik')->find($tanahId);
        if (!$t) return ['tanah_id' => $tanahId, 'nama' => '-', 'nik' => null, 'nomor_urut' => null];

        return [
            'tanah_id'   => $t->id,
            'nomor_urut' => $t->nomor_urut,
            'nama'       => optional($t->pemilik)->nama_lengkap ?? ($t->pemilik_nama ?? '-'),
            'nik'        => optional($t->pemilik)->nik, // bisa null kalau tak ada
        ];
    }

    private function buildPemilikPreviewFromWargaId(?int $wargaId, ?string $fallbackNama = null): array
    {
        if ($wargaId) {
            $w = \App\Models\Warga::select('id','nama_lengkap','nik')->find($wargaId);
            if ($w) {
                return ['warga_id' => $w->id, 'nama' => $w->nama_lengkap, 'nik' => $w->nik];
            }
        }
        return ['warga_id' => $wargaId, 'nama' => $fallbackNama ?? '-', 'nik' => null];
    }

    // ======== T A N A H ========

        // POST /api/proposals/tanah
        public function proposeTanahCreate(Request $r)
    {
        $data = $r->validate([
            'nomor_urut'        => 'nullable|string|max:64',
            'warga_id'          => 'required|exists:warga,id',
            'nama_pemilik_text' => 'nullable|string|max:255',
            'jumlah_m2'         => 'nullable|numeric|min:0', // akan diabaikan saat apply
            'keterangan'        => 'nullable|string',

            'bidang'                             => 'nullable|array|min:1',
            'bidang.*.luas_m2'                   => 'required_with:bidang|numeric|min:0.01',
            'bidang.*.status_hak'                => 'required_with:bidang|in:HM,HGB,HP,HGU,HPL,MA,VI,TN',
            'bidang.*.penggunaan'                => 'required_with:bidang|in:PERUMAHAN,PERDAGANGAN_JASA,PERKANTORAN,INDUSTRI,FASILITAS_UMUM,SAWAH,TEGALAN,PERKEBUNAN,PETERNAKAN_PERIKANAN,HUTAN_BELUKAR,HUTAN_LINDUNG,MUTASI_TANAH,TANAH_KOSONG,LAIN_LAIN',
            'bidang.*.keterangan'                => 'nullable|string',

            // geometri yang didukung → akan dinormalisasi ke "feature"
            'bidang.*.geojson_id'                => 'nullable|exists:geojson,id',
            'bidang.*.geojson_feature'           => 'nullable|array',
            'bidang.*.geojson'                   => 'nullable|array',
            'bidang.*.geometry'                  => 'nullable|array',
            'bidang.*.geojson_nama'              => 'nullable|string|max:255',
            'bidang.*.srid'                      => 'nullable|integer|in:4326',
        ]);

        if (empty($data['warga_id']) && empty($data['nama_pemilik_text'])) {
            return response()->json(['message' => 'Harus memilih warga_id atau mengisi nama_pemilik_text.'], 422);
        }

        if (!empty($data['bidang'])) {
            $data['bidang'] = collect($data['bidang'])->map(function ($b) {
                $b['status_hak'] = strtoupper($b['status_hak']);
                $b['penggunaan'] = strtoupper($b['penggunaan']);

                $geoSrc = $b['geojson_feature'] ?? $b['geojson'] ?? $b['geometry'] ?? null;
                if ($geoSrc) {
                    $feat = $this->normalizeFeature($geoSrc);
                    [$cx,$cy] = $this->centroidFromRing($feat['geometry']['coordinates'][0]);
                    $b['feature']  = $feat;
                    $b['centroid'] = [$cx,$cy];
                }
                unset($b['geojson_feature'], $b['geojson'], $b['geometry']);

                if (!isset($b['srid'])) $b['srid'] = 4326;
                return $b;
            })->all();
        }

       // ...
            unset($data['jumlah_m2']); // nilai turunan

            // >>> TAMBAH: preview pemilik berdasarkan warga_id / nama_pemilik_text
            $data['preview_pemilik'] = $this->buildPemilikPreviewFromWargaId(
                $data['warga_id'] ?? null,
                $data['nama_pemilik_text'] ?? null
            );

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

    public function proposeTanahUpdate(Request $r, $id)
    {
        $delta = $r->validate([
            'fields'                         => 'required|array',
            'fields.nomor_urut'              => 'sometimes|string|max:64',
            'fields.warga_id'                => 'sometimes|nullable|exists:warga,id',
            'fields.nama_pemilik_text'       => 'sometimes|nullable|string|max:255',
            'fields.jumlah_m2'               => 'sometimes|nullable|numeric|min:0',
            'fields.keterangan'              => 'sometimes|nullable|string',

            'fields.bidang_ops'                           => 'sometimes|array',
            'fields.bidang_ops.*.op'                      => 'required_with:fields.bidang_ops|in:create,update,delete',
            'fields.bidang_ops.*.id'                      => 'required_if:fields.bidang_ops.*.op,update,delete|integer|exists:bidang,id',
            'fields.bidang_ops.*.luas_m2'                 => 'required_if:fields.bidang_ops.*.op,create,update|numeric|min:0.01',
            'fields.bidang_ops.*.status_hak'              => 'required_if:fields.bidang_ops.*.op,create,update|in:HM,HGB,HP,HGU,HPL,MA,VI,TN',
            'fields.bidang_ops.*.penggunaan'              => 'required_if:fields.bidang_ops.*.op,create,update|in:PERUMAHAN,PERDAGANGAN_JASA,PERKANTORAN,INDUSTRI,FASILITAS_UMUM,SAWAH,TEGALAN,PERKEBUNAN,PETERNAKAN_PERIKANAN,HUTAN_BELUKAR,HUTAN_LINDUNG,MUTASI_TANAH,TANAH_KOSONG,LAIN_LAIN',
            'fields.bidang_ops.*.keterangan'              => 'nullable|string',

            'fields.bidang_ops.*.geojson_id'              => 'nullable|exists:geojson,id',
            'fields.bidang_ops.*.geojson_feature'         => 'nullable|array',
            'fields.bidang_ops.*.geojson'                 => 'nullable|array',
            'fields.bidang_ops.*.geometry'                => 'nullable|array',
            'fields.bidang_ops.*.geojson_nama'            => 'nullable|string|max:255',
            'fields.bidang_ops.*.srid'                    => 'nullable|integer|in:4326',
        ]);

        if (!empty($delta['fields']['bidang_ops'])) {
            $delta['fields']['bidang_ops'] = collect($delta['fields']['bidang_ops'])->map(function ($op) {
                if (isset($op['status_hak'])) $op['status_hak'] = strtoupper($op['status_hak']);
                if (isset($op['penggunaan'])) $op['penggunaan'] = strtoupper($op['penggunaan']);

                $geoSrc = $op['geojson_feature'] ?? $op['geojson'] ?? $op['geometry'] ?? null;
                if ($geoSrc) {
                    $feat = $this->normalizeFeature($geoSrc);
                    [$cx,$cy] = $this->centroidFromRing($feat['geometry']['coordinates'][0]);
                    $op['feature']  = $feat;            // akan disimpan ke geojson.feature_json saat approve
                    $op['centroid'] = [$cx,$cy];
                    unset($op['geojson_feature'], $op['geojson'], $op['geometry']);
                }
                if (!isset($op['srid'])) $op['srid'] = 4326;

                return $op;
            })->all();
        }

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
        // Ambil tanah + pemilik + jumlah bidang untuk snapshot
        $tanah = \App\Models\Tanah::with([
            'pemilik:id,nik,nama_lengkap'
        ])->withCount('bidang')->find($id);

        if (! $tanah) {
            return response()->json(['message' => 'Tanah tidak ditemukan'], 404);
        }

        // Kalau kamu punya kolom turunan jumlah_m2_computed pakai itu,
        // kalau tidak ada, fallback ke sum(luas_m2) dari relasi bidang
        $totalLuas = $tanah->jumlah_m2_computed
            ?? (float) \App\Models\Bidang::where('tanah_id', $tanah->id)->sum('luas_m2');

        $payload = [
            'reason' => $r->input('reason'),
            'snapshot' => [
                'tanah_id'     => $tanah->id,
                'nomor_urut'   => $tanah->nomor_urut,
                'bidang_count' => (int) $tanah->bidang_count,
                'total_luas_m2'=> (float) $totalLuas,
                'pemilik'      => [
                    'id'            => $tanah->warga_id,
                    'nik'           => $tanah->pemilik->nik ?? null,
                    'nama_lengkap'  => $tanah->pemilik->nama_lengkap
                        ?? ($tanah->nama_pemilik_text ?? null),
                ],
            ],
        ];

        $ar = \App\Models\ApprovalRequest::create([
            'module'       => 'tanah',
            'action'       => 'delete',
            'target_id'    => $tanah->id,
            'payload'      => $payload,
            'submitted_by' => $r->user()->id,
        ]);

        return response()->json([
            'message' => 'Proposal hapus tanah dibuat',
            'id'      => $ar->id,
            // opsional: echo kilat biar gampang dicek di FE/Postman
            'preview_pemilik' => $payload['snapshot']['pemilik'],
        ], 202);
    }

        // ======== B I D A N G  ========

    // POST /api/staff/proposals/tanah/{tanah}/bidang
    // POST /api/staff/proposals/tanah/{tanah}/bidang
    public function proposeBidangCreate(Request $r, $tanah)
    {
        // Pastikan id tanah valid & ada
        // Catatan: param $tanah bisa angka atau instance Tanah (jika pakai implicit binding).
        $tanahId = $tanah instanceof Tanah ? $tanah->id : (int) $tanah;
        $tanahRow = Tanah::findOrFail($tanahId); // 404 kalau tidak ada

        // Validasi input bidang
        $data = $r->validate([
            'luas_m2'      => 'required|numeric|min:0.01',
            'status_hak'   => 'required|in:HM,HGB,HP,HGU,HPL,MA,VI,TN',
            'penggunaan'   => 'required|in:PERUMAHAN,PERDAGANGAN_JASA,PERKANTORAN,INDUSTRI,FASILITAS_UMUM,SAWAH,TEGALAN,PERKEBUNAN,PETERNAKAN_PERIKANAN,HUTAN_BELUKAR,HUTAN_LINDUNG,MUTASI_TANAH,TANAH_KOSONG,LAIN_LAIN',
            'keterangan'   => 'nullable|string',

            // Boleh kirim 'geojson' (Feature) ATAU 'geometry' (Polygon)
            'geojson'      => 'required_without:geometry|array',
            'geometry'     => 'required_without:geojson|array',

            'geojson_nama' => 'nullable|string|max:255',
            'srid'         => 'nullable|integer|in:4326',
            'empat_titik'  => 'nullable|boolean',
        ]);

        // Normalisasi enum ke UPPER (kalau FE salah casing)
        $data['status_hak'] = strtoupper($data['status_hak']);
        $data['penggunaan'] = strtoupper($data['penggunaan']);

        // Normalisasi geometri → Feature{ Polygon } + tutup ring
        $feature = $this->normalizeFeature($data['geojson'] ?? $data['geometry']);

        // Opsional: wajibkan tepat 4 sudut (ring tertutup = 5 titik)
        if ($r->boolean('empat_titik', false)) {
            $this->assertFourCorners($feature);
        }

        // Hitung centroid sederhana untuk preview
        [$cx, $cy] = $this->centroidFromRing($feature['geometry']['coordinates'][0]);

        // ⛔️ Penting: SELALU overwrite tanah_id dari route, JANGAN ambil dari FE
        $payload = [
            'tanah_id'     => $tanahRow->id,
            'luas_m2'      => (float) $data['luas_m2'],
            'status_hak'   => $data['status_hak'],
            'penggunaan'   => $data['penggunaan'],
            'keterangan'   => $data['keterangan'] ?? null,
            'srid'         => (int)($data['srid'] ?? 4326),
            'geojson_nama' => $data['geojson_nama'] ?? null,
            'feature'      => $feature,
            'centroid'     => [$cx, $cy],
        ];

       // ... setelah $payload dibuat
        $payload['preview_pemilik'] = $this->buildPemilikPreviewFromTanahId($tanahRow->id);

        $ar = ApprovalRequest::create([
            'module'       => 'bidang',
            'action'       => 'create',
            'payload'      => $payload,
            'submitted_by' => $r->user()->id,
        ]);


        return response()->json([
            'message' => 'Proposal tambah bidang dibuat, menunggu persetujuan kepala.',
            'id'      => $ar->id,
            'status'  => $ar->status,
        ], 202);
    }

    /**
     * GET /api/bidang/{id}
     * Kembalikan detail bidang + info minimal tanah & geojson untuk prefill form edit.
     */
    // impor relasi pemilik
// Bidang::with(['tanah:id,warga_id,nomor_urut,keterangan', 'tanah.pemilik:id,nik,nama_lengkap', 'geojson:id,nama'])

    public function proposeBidangShow($id)
    {
        $b = Bidang::with([
            'tanah:id,warga_id,nomor_urut,keterangan',
            'tanah.pemilik:id,nik,nama_lengkap',
            'geojson:id,nama'
        ])->findOrFail($id);

        return response()->json([
            'id'          => $b->id,
            'tanah_id'    => $b->tanah_id,
            'geojson_id'  => $b->geojson_id,
            'luas_m2'     => (float) $b->luas_m2,
            'status_hak'  => $b->status_hak,
            'penggunaan'  => $b->penggunaan,
            'keterangan'  => $b->keterangan,
            'tanah'       => [
                'id'         => $b->tanah->id,
                'nomor_urut' => $b->tanah->nomor_urut,
                'pemilik'    => $b->tanah->pemilik ? [
                    'nik'  => $b->tanah->pemilik->nik,
                    'nama' => $b->tanah->pemilik->nama_lengkap,
                ] : null,
            ],
            'geojson'     => $b->geojson ? [
                'id'   => $b->geojson->id,
                'nama' => $b->geojson->nama,
            ] : null,
        ]);
    }



    // PATCH /api/staff/proposals/bidang/{id}
    public function proposeBidangUpdate(Request $r, $id)
    {
        $v = $r->validate([
            'luas_m2'     => 'sometimes|numeric|min:0.01',
            'status_hak'  => 'sometimes|in:HM,HGB,HP,HGU,HPL,MA,VI,TN',
            'penggunaan'  => 'sometimes|in:PERUMAHAN,PERDAGANGAN_JASA,PERKANTORAN,INDUSTRI,FASILITAS_UMUM,SAWAH,TEGALAN,PERKEBUNAN,PETERNAKAN_PERIKANAN,HUTAN_BELUKAR,HUTAN_LINDUNG,MUTASI_TANAH,TANAH_KOSONG,LAIN_LAIN',
            'keterangan'  => 'sometimes|nullable|string',
            'geojson'     => 'sometimes|array',
            'geometry'    => 'sometimes|array',
            'geojson_nama'=> 'sometimes|nullable|string|max:255',
            'srid'        => 'sometimes|integer|in:4326',
            'empat_titik' => 'nullable|boolean',
            'fields'      => 'sometimes|array',
        ]);

        $fields = $v['fields'] ?? [];
        foreach (['luas_m2','status_hak','penggunaan','keterangan','geojson_nama','srid'] as $k) {
            if ($r->has($k)) $fields[$k] = $v[$k];
        }

        // bila ada geometri baru, normalisasi & (opsional) cek 4 titik
        if ($r->has('geojson') || $r->has('geometry')) {
            $feature = $this->normalizeFeature($v['geojson'] ?? $v['geometry']);
            if ($r->boolean('empat_titik', true)) {
                $this->assertFourCorners($feature);
            }
            [$cx,$cy] = $this->centroidFromRing($feature['geometry']['coordinates'][0]);
            $fields['feature']  = $feature;
            $fields['centroid'] = [$cx,$cy];
        }

        // ... setelah susun $fields
        $b = \App\Models\Bidang::select('id','tanah_id')->findOrFail($id);
        $fields['preview_pemilik'] = $this->buildPemilikPreviewFromTanahId($b->tanah_id);

        if (empty($fields)) {
            return response()->json(['message' => 'Tidak ada perubahan dikirim'], 422);
        }

        $ar = \App\Models\ApprovalRequest::create([
            'module'    => 'bidang',
            'action'    => 'update',
            'target_id' => $id,
            'payload'   => $fields,
            'submitted_by' => $r->user()->id,
        ]);


        return response()->json(['message' => 'Proposal update bidang dibuat', 'id' => $ar->id], 202);
    }

    // DELETE /api/staff/proposals/bidang/{id}
    public function proposeBidangDelete(Request $r, $id)
    {
        $b = \App\Models\Bidang::select('id','tanah_id')->findOrFail($id);

        $ar = \App\Models\ApprovalRequest::create([
            'module'    => 'bidang',
            'action'    => 'delete',
            'target_id' => $id,
            'payload'   => [
                'reason'          => $r->input('reason'),
                'preview_pemilik' => $this->buildPemilikPreviewFromTanahId($b->tanah_id),
            ],
            'submitted_by' => $r->user()->id,
        ]);


        return response()->json(['message' => 'Proposal hapus bidang dibuat', 'id' => $ar->id], 202);
    }

    /* ================== Helpers GeoJSON ================== */

    /** Terima Feature/Geometry, hasilkan Feature{ Polygon } dengan ring tertutup */
    private function normalizeFeature($g): array
    {
        if (!$g) {
            throw ValidationException::withMessages(['geojson' => ['GeoJSON/Geometry wajib ada.']]);
        }

        // bungkus ke Feature jika yang datang masih Geometry
        if (($g['type'] ?? null) !== 'Feature') {
            $g = ['type' => 'Feature', 'properties' => (object)[], 'geometry' => $g];
        }

        $geom = $g['geometry'] ?? null;
        if (!$geom || ($geom['type'] ?? '') !== 'Polygon') {
            throw ValidationException::withMessages(['geojson' => ['Harus Polygon.']]);
        }

        $ring = $geom['coordinates'][0] ?? [];
        if (count($ring) < 4) {
            throw ValidationException::withMessages(['geojson' => ['Koordinat kurang (minimal 4 titik).']]);
        }

        // tutup ring (titik terakhir = titik awal)
        $first = $ring[0]; $last = end($ring);
        if ($first[0] !== $last[0] || $first[1] !== $last[1]) {
            $ring[] = $first;
            $geom['coordinates'][0] = $ring;
            $g['geometry'] = $geom;
        }

        return $g;
    }

    /** Validasi polygon tepat 4 sudut (ring tertutup = 5 koordinat) */
    private function assertFourCorners(array $feature): void
    {
        $ring = $feature['geometry']['coordinates'][0] ?? [];
        if (count($ring) !== 5) {
            throw ValidationException::withMessages([
                'geojson' => ['Wajib tepat 4 titik (ring tertutup = 5 koordinat).']
            ]);
        }
    }

    /** Centroid sederhana dari ring (abaikan titik penutup) */
    private function centroidFromRing(array $ring): array
    {
        $n = count($ring) - 1; // abaikan titik penutup
        $sx = 0; $sy = 0;
        for ($i = 0; $i < $n; $i++) {
            $sx += $ring[$i][0]; // lng
            $sy += $ring[$i][1]; // lat
        }
        return [$sx / $n, $sy / $n]; // [lng, lat]
    }


    // ======== W A R G A (TETAP) ========
    // ... (kode Warga & helper saveFotoKtpToPublic kamu tetap seperti sebelumnya)

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
        $warga = Warga::find($id);
        if (!$warga) {
            return response()->json(['message' => 'Warga tidak ditemukan'], 404);
        }

        $ar = ApprovalRequest::create([
            'module'       => 'warga',
            'action'       => 'delete',
            'target_id'    => $id,
            'payload'      => [
                'reason'   => $r->input('reason'),
                'snapshot' => [
                    'nik'           => $warga->nik,
                    'nama_lengkap'  => $warga->nama_lengkap,
                    // tambahkan field lain yang mau ditampilkan
                ],
            ],
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
