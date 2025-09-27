<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; // penting untuk auth
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasFactory, Notifiable,HasApiTokens;

    protected $fillable = ['name','email','role','password'];
    protected $hidden = ['password','remember_token'];

    protected $casts = [
        // jika pakai Sanctum/laravel terbaru, tambahkan cast lain sesuai kebutuhan
    ];

    // otomatis hash saat set password
    public function setPasswordAttribute($value)
    {
        // hindari double-hash bila value sudah ter-hash
        $this->attributes['password'] = Hash::needsRehash($value)
            ? Hash::make($value)
            : $value;
    }
}
