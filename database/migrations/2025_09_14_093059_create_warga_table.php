<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('warga', function (Blueprint $t) {
            $t->id();
            $t->string('nama_lengkap');
            $t->enum('jenis_kelamin', ['L','P'])->nullable();
            $t->enum('status_perkawinan', ['BELUM KAWIN','KAWIN','CERAI HIDUP','CERAI MATI'])->nullable();

            $t->string('tempat_lahir')->nullable();
            $t->date('tanggal_lahir')->nullable();

            $t->string('agama')->nullable();
            $t->string('pendidikan_terakhir')->nullable();
            $t->string('pekerjaan')->nullable();
            $t->longText('foto_ktp')->nullable();
            $t->enum('kewarganegaraan', ['WNI','WNA'])->default('WNI');
            $t->string('alamat_lengkap')->nullable();

            $t->string('nik', 16)->nullable()->unique(); // 16 digit jika ada
            $t->text('keterangan')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('warga');
    }
};
