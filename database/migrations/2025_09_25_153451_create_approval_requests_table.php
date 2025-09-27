<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $t) {
            $t->id();

            // Target modul & aksi
            $t->enum('module', ['tanah','warga','geojson']);     // modul yang diubah
            $t->enum('action', ['create','update','delete']);    // jenis operasi
            $t->unsignedBigInteger('target_id')->nullable();     // id record target (untuk update/delete)

            // Payload usulan (isi perubahan)
            $t->json('payload'); // simpan body usulan (field-field yang diajukan)

            // Pengaju & status
            $t->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $t->enum('status', ['pending','approved','rejected'])->default('pending');

            // Review/keputusan oleh kepala
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->text('review_note')->nullable();

            // Penerapan ke tabel utama
            $t->timestamp('applied_at')->nullable();    // kapan diterapkan (sukses)
            $t->text('apply_error')->nullable();        // alasan jika gagal apply (otomatis reject)

            $t->timestamps();

            // Index untuk performa
            $t->index(['module','status','created_at']);
            $t->index(['module','action']);
            $t->index(['target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
