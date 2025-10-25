<?php
// database/migrations/2025_09_14_000200_create_tanah_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tanah', function (Blueprint $table) {
            $table->id();

            // Identitas entri A.6
            $table->string('nomor_urut', 64)->unique();
            $table->foreignId('warga_id')->nullable()
                  ->constrained('warga')->nullOnDelete();

            // Luas total (opsional; bisa dihitung dari SUM(bidang.luas_m2))
            $table->decimal('jumlah_m2', 14, 2)->nullable();

            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['warga_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('tanah');
    }
};
