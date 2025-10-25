<?php
// database/migrations/2025_09_28_000100_create_bidang_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('bidang', function (Blueprint $t) {
            // id bidang tetap UUID (tidak masalah)
            $t->id();

            // many bidang -> one tanah (tanah.id = bigInt)
            $t->foreignId('tanah_id')->constrained('tanah')->cascadeOnDelete();

            // one bidang -> one geojson (geojson.id = bigInt UNSIGNED)
            $t->foreignId('geojson_id')->nullable()->unique()
              ->constrained('geojson')->nullOnDelete();

            $t->decimal('luas_m2', 14, 2);

            $t->enum('status_hak', ['HM','HGB','HP','HGU','HPL','MA','VI','TN']);
            $t->enum('penggunaan', [
                'PERUMAHAN','PERDAGANGAN_JASA','PERKANTORAN','INDUSTRI','FASILITAS_UMUM',
                'SAWAH','TEGALAN','PERKEBUNAN','PETERNAKAN_PERIKANAN',
                'HUTAN_BELUKAR','HUTAN_LINDUNG','MUTASI_TANAH','TANAH_KOSONG','LAIN_LAIN'
            ]);

            $t->text('keterangan')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->index(['tanah_id','status_hak']);
            $t->index(['tanah_id','penggunaan']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('bidang');
    }
};
