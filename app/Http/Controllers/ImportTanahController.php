<?php

namespace App\Http\Controllers;

use App\Models\Warga;
use App\Models\Tanah;
use App\Models\Bidang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportTanahController extends Controller
{
    /**
     * POST /api/staff/management-warga/import/csv
     *
     * Body (multipart/form-data):
     *  - file : warga_import.csv
     *
     * Struktur kolom (header) yang diharapkan:
     *  nik, nama_lengkap, jenis_kelamin, status_perkawinan,
     *  tempat_lahir, tanggal_lahir, agama, pendidikan_terakhir,
     *  pekerjaan, alamat_lengkap, keterangan
     */
    public function importWargaCsv(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120', // max ±5MB
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        if (! $handle = fopen($path, 'r')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal membuka file CSV.',
            ], 422);
        }

        // Baca header
        $header = fgetcsv($handle, 0, ',');
        if (! $header) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Header CSV tidak ditemukan.',
            ], 422);
        }

        // Normalisasi header ke lowercase + trim
        $header = array_map(fn ($h) => strtolower(trim($h)), $header);
        $index  = array_flip($header);

        // Wajib ada kolom-kolom berikut (sesuai file warga_import.csv yang kita buat)
        $requiredCols = ['nik', 'nama_lengkap', 'alamat_lengkap'];
        foreach ($requiredCols as $col) {
            if (! isset($index[$col])) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Kolom '{$col}' wajib ada di header CSV.",
                ], 422);
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $rowNum  = 1; // sudah ambil header

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                $rowNum++;

                // Skip baris kosong total
                $nonEmpty = array_filter($row, fn ($v) => $v !== null && trim($v) !== '');
                if (count($nonEmpty) === 0) {
                    $skipped++;
                    continue;
                }

                $nik   = isset($index['nik']) ? trim((string)($row[$index['nik']] ?? '')) : '';
                $nama  = trim((string)($row[$index['nama_lengkap']] ?? ''));
                $alamat = trim((string)($row[$index['alamat_lengkap']] ?? ''));

                // Minimal harus punya nama atau NIK
                if ($nik === '' && $nama === '') {
                    $skipped++;
                    continue;
                }

                $payload = [
                    'nama_lengkap'        => $nama !== '' ? $nama : 'TANPA NAMA',
                    'alamat_lengkap'      => $alamat !== '' ? $alamat : null,
                    'jenis_kelamin'       => isset($index['jenis_kelamin'])
                        ? strtoupper(trim((string)($row[$index['jenis_kelamin']] ?? ''))) ?: null
                        : null,
                    'status_perkawinan'   => isset($index['status_perkawinan'])
                        ? strtoupper(trim((string)($row[$index['status_perkawinan']] ?? ''))) ?: null
                        : null,
                    'tempat_lahir'        => isset($index['tempat_lahir'])
                        ? trim((string)($row[$index['tempat_lahir']] ?? '')) ?: null
                        : null,
                    'tanggal_lahir'       => isset($index['tanggal_lahir'])
                        ? trim((string)($row[$index['tanggal_lahir']] ?? '')) ?: null
                        : null,
                    'agama'               => isset($index['agama'])
                        ? trim((string)($row[$index['agama']] ?? '')) ?: null
                        : null,
                    'pendidikan_terakhir' => isset($index['pendidikan_terakhir'])
                        ? trim((string)($row[$index['pendidikan_terakhir']] ?? '')) ?: null
                        : null,
                    'pekerjaan'           => isset($index['pekerjaan'])
                        ? trim((string)($row[$index['pekerjaan']] ?? '')) ?: null
                        : null,
                    'keterangan'          => isset($index['keterangan'])
                        ? trim((string)($row[$index['keterangan']] ?? '')) ?: null
                        : null,
                    // kewarganegaraan biarkan default 'WNI' kalau tidak ada di CSV
                ];

                // Cari berdasarkan NIK kalau ada
                $warga = null;
                if ($nik !== '') {
                    $warga = Warga::where('nik', $nik)->first();
                }

                if ($warga) {
                    // Update data lama
                    $warga->fill($payload);
                    $warga->save();
                    $updated++;
                } else {
                    // Buat baru
                    $warga = new Warga();
                    $warga->fill($payload);
                    $warga->nik = $nik !== '' ? $nik : null;
                    $warga->save();
                    $created++;
                }
            }

            DB::commit();

            return response()->json([
                'status'  => 'ok',
                'message' => 'Import warga selesai.',
                'summary' => [
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'total_rows' => $created + $updated + $skipped,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat import warga: '.$e->getMessage(),
                'row'     => $rowNum,
            ], 500);
        } finally {
            fclose($handle);
        }
    }

    /**
     * POST /api/staff/management-tanah/import/csv
     *
     * Body (multipart/form-data):
     *  - file : tanah_import.csv
     *
     * Struktur kolom (header) yang diharapkan:
     *  nomor_urut, nik_pemilik, nama_pemilik, jumlah_m2,
     *  keterangan, status_hak_default, penggunaan_default, catatan_import
     *
     * Catatan:
     *  - Hanya mengisi tabel `tanah` (belum membuat `bidang`).
     *  - Link ke `warga` via nik_pemilik (prioritas) atau nama_pemilik
     */
     public function importTanahCsv(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $path = $request->file('file')->getRealPath();

        if (! is_readable($path)) {
            return response()->json([
                'message' => 'File tidak bisa dibaca.',
            ], 422);
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return response()->json([
                'message' => 'Gagal membuka file CSV.',
            ], 422);
        }

        // ==== Baca header ====
        $header = fgetcsv($handle, 0, ',');
        if (! $header) {
            fclose($handle);
            return response()->json([
                'message' => 'Header CSV kosong / tidak valid.',
            ], 422);
        }

        // Normalisasi nama kolom ke lowercase
        $header = array_map(fn ($h) => strtolower(trim($h)), $header);
        $idx = array_flip($header);

        // Pastikan kolom minimal tersedia
        $requiredCols = ['nomor_urut', 'nama_pemilik', 'nik', 'jml_m2'];
        foreach ($requiredCols as $col) {
            if (! isset($idx[$col])) {
                fclose($handle);
                return response()->json([
                    'message' => "Kolom '{$col}' wajib ada di header CSV.",
                ], 422);
            }
        }

        $createdTanah = 0;
        $updatedTanah = 0;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                // Skip baris kosong
                if (count(array_filter($row, fn ($v) => trim($v) !== '')) === 0) {
                    continue;
                }

                // Helper ambil nilai kolom
                $get = function (string $col) use ($idx, $row) {
                    if (! isset($idx[$col])) {
                        return null;
                    }
                    return $row[$idx[$col]] ?? null;
                };

                $nomorUrut = trim((string) $get('nomor_urut'));
                if ($nomorUrut === '') {
                    // Tanpa nomor_urut, skip
                    continue;
                }

                $namaPemilik = trim((string) $get('nama_pemilik'));
                $nikRaw      = trim((string) $get('nik'));
                $nik         = preg_replace('/\D/', '', $nikRaw); // hanya digit
                $jumlahM2    = (float) str_replace(',', '.', (string) $get('jml_m2'));
                $keterangan  = $get('keterangan');

                // ==== 1) Cari / buat warga ====
                $warga = null;

                if ($nik !== '') {
                    $warga = Warga::firstOrCreate(
                        ['nik' => $nik],
                        [
                            'nama_lengkap'    => $namaPemilik ?: $nik,
                            'kewarganegaraan' => 'WNI',
                        ]
                    );
                } elseif ($namaPemilik !== '') {
                    // Kalau tanpa NIK, pakai nama (tidak unik tapi cukup untuk migrasi awal)
                    $warga = Warga::firstOrCreate(
                        [
                            'nama_lengkap' => $namaPemilik,
                            'nik'          => null,
                        ],
                        [
                            'kewarganegaraan' => 'WNI',
                        ]
                    );
                }

                // ==== 2) Buat / update Tanah berdasarkan nomor_urut ====
                $tanah = Tanah::firstOrNew(['nomor_urut' => $nomorUrut]);
                $isNew = ! $tanah->exists;

                if ($warga) {
                    $tanah->warga_id = $warga->id;
                }

                if ($jumlahM2 > 0) {
                    $tanah->jumlah_m2 = $jumlahM2;
                }

                if ($keterangan !== null && $keterangan !== '') {
                    $tanah->keterangan = $keterangan;
                }

                $tanah->save();

                if ($isNew) {
                    $createdTanah++;
                } else {
                    $updatedTanah++;
                }

                // ==== 3) (Opsional) reset bidang hasil impor sebelumnya ====
                // Supaya impor ulang CSV yang sama tidak dobel bidang
                $tanah->bidang()->delete();

                // ==== 4) Tentukan status_hak & penggunaan "utama" ====

                // 4a. status_hak dari kolom HM,HGB,HP,HGU,HPL,MA,VI,TN
                $statusCols = [
                    'hm'  => 'HM',
                    'hgb' => 'HGB',
                    'hp'  => 'HP',
                    'hgu' => 'HGU',
                    'hpl' => 'HPL',
                    'ma'  => 'MA',
                    'vi'  => 'VI',
                    'tn'  => 'TN',
                ];

                $statusHak = 'HM'; // default
                foreach ($statusCols as $col => $enumVal) {
                    $val = (float) str_replace(',', '.', (string) $get($col));
                    if ($val > 0) {
                        $statusHak = $enumVal;
                        break;
                    }
                }

                // 4b. penggunaan = kolom penggunaan terbesar
                $penggunaanCols = [
                    'perumahan'             => 'PERUMAHAN',
                    'perdagangan_jasa'      => 'PERDAGANGAN_JASA',
                    'perkantoran'           => 'PERKANTORAN',
                    'industri'              => 'INDUSTRI',
                    'fasilitas_umum'        => 'FASILITAS_UMUM',
                    'sawah'                 => 'SAWAH',
                    'tegalan'               => 'TEGALAN',
                    'perkebunan'            => 'PERKEBUNAN',
                    'peternakan_perikanan'  => 'PETERNAKAN_PERIKANAN',
                    'hutan_belukar'         => 'HUTAN_BELUKAR',
                    'hutan_lindung'         => 'HUTAN_LINDUNG',
                    'mutasi_tanah'          => 'MUTASI_TANAH',
                    'tanah_kosong'          => 'TANAH_KOSONG',
                    'lain_lain'             => 'LAIN_LAIN',
                ];

                $penggunaan   = 'LAIN_LAIN';
                $maxPenggunaan = 0.0;

                foreach ($penggunaanCols as $col => $enumVal) {
                    $val = (float) str_replace(',', '.', (string) $get($col));
                    if ($val > $maxPenggunaan) {
                        $maxPenggunaan = $val;
                        $penggunaan    = $enumVal;
                    }
                }

                // ==== 5) Buat satu Bidang "placeholder" untuk tanah ini ====
                // GeoJSON belum ada -> geojson_id = null
                if ($jumlahM2 > 0) {
                    Bidang::create([
                        'tanah_id'   => $tanah->id,
                        'geojson_id' => null,
                        'luas_m2'    => $jumlahM2,
                        'status_hak' => $statusHak,
                        'penggunaan' => $penggunaan,
                        'keterangan' => 'Impor awal buku tanah (placeholder 1 bidang per tanah)',
                    ]);
                }
            }

            fclose($handle);
            DB::commit();

            return response()->json([
                'message'        => 'Impor CSV berhasil.',
                'created_tanah'  => $createdTanah,
                'updated_tanah'  => $updatedTanah,
            ]);
        } catch (\Throwable $e) {
            fclose($handle);
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal mengimpor CSV: ' . $e->getMessage(),
            ], 500);
        }
    }
}
