<?php
// database/migrations/2025_09_28_000000_create_geojson_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('geojson', function (Blueprint $t) {
            $t->id(); // sudah primary
            $t->string('nama')->nullable();

            // GeoJSON mentah (Polygon/Feature/FeatureCollection)
            $t->longText('feature_json');
            $t->unsignedSmallInteger('srid')->default(4326);

            // ringkasan spasial (opsional tapi membantu)
            $t->decimal('centroid_lng', 11, 8)->nullable();
            $t->decimal('centroid_lat', 10, 8)->nullable();

            $t->unsignedInteger('versi')->default(1);

            // self reference (versi sebelumnya). Samakan tipe dengan id() = BIGINT
            $t->foreignId('parent_id')->nullable()
              ->constrained('geojson')->nullOnDelete();

            $t->json('properties')->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->index(['srid','versi']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('geojson');
    }
};
