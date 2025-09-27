<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();

            $t->string('name');                       // nama lengkap
            $t->string('email')->unique();        // untuk login
            $t->enum('role', ['kepala','staff']);   // peran: kepala desa / sekdes
            $t->string('password');                  // disimpan hash (bcrypt/argon)

            $t->rememberToken();                     // opsional, untuk "remember me"
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
