# Sistem Pencatatan Tanah Desa – Backend

Backend API berbasis **Laravel** untuk sistem pencatatan tanah desa (buku tanah desa).  
Aplikasi ini digunakan untuk mengelola data **warga**, **tanah**, **bidang tanah**, dan (opsional) **batas bidang dalam bentuk GeoJSON**, yang dapat diintegrasikan dengan frontend (misalnya dashboard Next.js).

---

## 🏗️ Struktur Project

```txt
tanah_desa/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── AuthController.php        # Login / logout / profile
│   │   │   │   ├── WargaController.php       # CRUD warga
│   │   │   │   ├── TanahController.php       # CRUD tanah (buku tanah)
│   │   │   │   ├── BidangController.php      # CRUD bidang tanah
│   │   │   │   └── GeojsonController.php     # (Opsional) data geojson bidang
│   │   │   └── Controller.php                # Base controller
│   ├── Models/
│   │   ├── Warga.php                         # Subjek hak (pemilik tanah)
│   │   ├── Tanah.php                         # Buku tanah desa (nomor_urut, luas, dll.)
│   │   ├── Bidang.php                        # Bidang-bidang per tanah
│   │   └── Geojson.php                       # (Opsional) fitur GeoJSON dan centroid
│   └── ...
├── config/
│   └── ...
├── database/
│   ├── migrations/                           # Skema tabel warga, tanah, bidang, geojson
│   └── seeders/                              # (Opsional) data awal contoh
├── routes/
│   ├── api.php                               # Definisi route API (prefix /api)
│   └── web.php
├── public/
├── resources/
│   ├── views/                                # Blade view default Laravel (kalau dipakai)
│   └── ...
├── storage/
├── .env.example                              # Contoh konfigurasi environment
├── artisan
├── composer.json
├── package.json
└── README.md
