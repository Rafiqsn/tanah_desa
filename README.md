# 🌱 Tanah_desa

**tanah_desa** adalah aplikasi web berbasis PHP & Blade yang dirancang untuk membantu pengelolaan data tanah desa secara efektif dan efisien. Dokumentasi berikut akan menyediakan penjelasan terstruktur dan mudah dipahami, menggunakan ikon dan emoji agar pengalaman membaca semakin nyaman 😊.

---

## 🗂️ Daftar Isi

1. [📖 Deskripsi Proyek](#deskripsi-proyek)
2. [✨ Fitur Utama](#fitur-utama)
3. [🚀 Instalasi & Setup](#instalasi--setup)
4. [📁 Struktur Proyek](#struktur-proyek)
5. [🛠️ Cara Penggunaan](#cara-penggunaan)
6. [🤝 Kontribusi](#kontribusi)
7. [📜 Lisensi](#lisensi)
8. [📬 Kontak & Dukungan](#kontak--dukungan)

---

## 📖 Deskripsi Proyek

**tanah_desa** bertujuan memudahkan perangkat desa dalam melakukan pengelolaan, dokumentasi, dan pelaporan aset tanah desa berdasarkan data yang akurat dan terpusat. Semua fitur didesain agar operator dapat bekerja cepat dan semua proses administrasi menjadi transparan serta dapat dipantau oleh pihak terkait.

---

## ✨ Fitur Utama

Setiap fitur diberikan penjelasan detail, lengkap dengan emoji sebagai gambaran visual:

### 📝 1. Manajemen Data Tanah
- **Tambah Data Tanah Baru 🆕**  
  - Input detail tanah: lokasi, luas, status kepemilikan, peta, sertifikat, dan dokumen pendukung.
  - Validasi data otomatis untuk mengurangi kesalahan input.
- **Update/Hapus Data Tanah 🔄❌**  
  - Edit data lapangan jika terdapat perubahan sertifikat, kepemilikan, atau status fisik tanah.
  - Hapus data jika tidak berlaku lagi, dengan konfirmasi ganda untuk keamanan.
- **Pencarian dan Filter 🔍**  
  - Cari data tanah berdasarkan kata kunci, filter berdasarkan dusun, status, atau tahun.
  - Sorting tabel untuk memudahkan pengambilan data paling relevan.

### 📑 2. Pengelolaan Dokumen
- **Unggah Dokumen Pendukung 📎**  
  - Upload sertifikat, surat keterangan, foto lokasi, dan peta digital.
  - Preview dokumen sebelum simpan agar memastikan file benar.
- **Download & Cetak Dokumen 🖨️**  
  - Download file yang diunggah untuk kebutuhan laporan, atau cetak dokumen langsung dari aplikasi.

### 🧾 3. Laporan & Rekapitulasi
- **Generate Laporan Otomatis 📊**  
  - Buat laporan PDF, Excel atau CSV berdasarkan periode, lokasi, atau kriteria khusus.
  - Laporan dilengkapi grafik statistik dan tabel detail.
- **Rekap Data Tanah 📋**  
  - Lihat rekap jumlah tanah per dusun/desa, total luas, dan status kepemilikan.

### 👥 4. Hak Akses Pengguna
- **Level Hak Akses 👤🛡️**  
  - Role "Admin": Kelola semua data dan pengguna.
  - Role "Operator": Input dan edit data serta membuat laporan.
  - Role "Viewer": Hanya dapat melihat data tanpa bisa mengedit.
- **Manajemen Pengguna 🚪**  
  - Tambah, edit, dan nonaktifkan akun sesuai kebutuhan perangkat desa.

### 📊 5. Dashboard Interaktif
- **Statistik & Grafik 📈**  
  - Visualisasi data berbentuk diagram batang, lingkaran, dan peta interaktif.
  - Statistik real time: jumlah tanah berdasarkan status, lokasi, dan tahun.
- **Notifikasi Aktivitas 🔔**  
  - Notifikasi ketika data diubah, laporan terbuat, ataupun dokumen diunggah.

### 🛡️ 6. Keamanan Data  
- **Enkripsi & Backup Otomatis 🔒💾**  
  - Data sensitif dienkripsi, fitur backup database terjadwal.
  - History perubahan data terekam untuk audit.

---

## 🚀 Instalasi & Setup

Langkah berikut untuk menjalankan aplikasi:

```bash
# 1. Clone repository
git clone https://github.com/Rafiqsn/tanah_desa.git
cd tanah_desa

# 2. Install dependencies via Composer
composer install

# 3. Konfigurasi Environment
cp .env.example .env
# Edit .env sesuai akses database lokal Anda

# 4. Generate Key & Migrasi Database
php artisan key:generate
php artisan migrate

# 5. Jalankan Server Lokal
php artisan serve
# Default akses di: http://localhost:8000
```
**Note:** Pastikan sudah install PHP >=7.x dan Composer di komputer Anda!

---

## 📁 Struktur Proyek

Struktur utama aplikasi tanah_desa:

```
tanah_desa/
├── app/                # Fungsi utama (Model, Controller, Service)
├── resources/
│   └── views/          # Template Blade untuk frontend
├── public/             # Asset publik: gambar, css, js, dokumen
├── database/
│   └── migrations/     # File migrasi & seed database
├── routes/             # Definisi endpoint web & api
├── .env                # Konfigurasi environment
├── composer.json       # Manajemen package PHP
└── README.md           # Dokumentasi
```

**Penjelasan Singkat:**  
- `app/`: Logika bisnis dan pengolahan data.  
- `resources/views/`: Tampilan antarmuka berbasis Blade.  
- `public/`: File yang bisa diakses langsung oleh user (gambar, style, dokumen).  
- `database/`: Script migrasi & seed data awal.  
- `routes/`: Pengaturan route web dan api.  
- `.env`: Rahasia konfigurasi (jangan upload ke publik).

---

## 🛠️ Cara Penggunaan

1. **Login ke aplikasi** menggunakan akun yang telah dibuat oleh administrator.
2. **Tambah Data Tanah:** Masuk menu "Manajemen Tanah" lalu klik tombol tambah ➕, isi semua field lalu simpan.
3. **Edit/Hapus Data:** Pilih data dari list, klik ikon pensil ✏️ untuk edit, atau ikon tong sampah 🗑️ untuk hapus setelah konfirmasi.
4. **Unggah Dokumen:** Di detail data tanah, klik "Upload Dokumen", pilih file lalu simpan.
5. **Laporan & Rekap:** Menu "Laporan" untuk generate dokumen PDF/Excel berdasarkan filter yang diinginkan.
6. **Dashboard:** Pantau statistik, aktivitas terakhir, dan notifikasi melalui dashboard utama.

---

## 🤝 Kontribusi

Kontribusi sangat terbuka! Ikuti langkah berikut:

1. Fork repository & buat branch baru (misal: `fitur-baru`).
2. Lakukan perubahan (commit dengan pesan jelas).
3. Pull Request dengan deskripsi singkat dan detail fitur/bugfix.
4. Tunggu review dari maintainer 😃.

**Panduan Coding:**  
- Ikuti standar PSR untuk PHP.
- Jaga penamaan variabel & fungsi jelas.
- Lakukan testing sebelum ajukan PR.

---

## 📜 Lisensi

Proyek ini berlisensi [MIT](LICENSE) — silakan gunakan dan modifikasi sesuai kebutuhan!

---



✨ **Terima kasih sudah menggunakan dan berkontribusi pada tanah_desa!** ✨
