<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('geojson', function (Blueprint $t) {
            $t->id();

            // Relasi ke tabel tanah (A.6)
            $t->foreignId('tanah_id')->constrained('tanah')->cascadeOnDelete();

            // Menyimpan GeoJSON (Feature atau Polygon) dari Leaflet
            // Contoh isi: {"type":"Feature","geometry":{...},"properties":{...}}
            $t->json('feature');

            // Info bantu untuk peta/rekap
            $t->decimal('centroid_lat', 10, 7)->nullable();
            $t->decimal('centroid_lng', 10, 7)->nullable();
            $t->decimal('luas_terhitung_m2', 14, 2)->nullable();

            $t->timestamps();

            // 1:1 per bidang tanah
            $t->unique('tanah_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geojson');
    }
};
