<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str; // ← penting

class Warga extends Model
{
    use HasFactory;

    protected $table = 'warga';

    protected $fillable = [
        'nama_lengkap','jenis_kelamin','status_perkawinan','tempat_lahir','tanggal_lahir',
        'agama','pendidikan_terakhir','pekerjaan','foto_ktp','kewarganegaraan',
        'alamat_lengkap','nik','keterangan',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
    ];

    // auto-append URL ke response JSON
    protected $appends = ['foto_ktp_url'];

    /* ================= Relations ================= */
    public function bidang()
    {
        return $this->hasMany(Tanah::class, 'warga_id');
    }

    public function tanah()
    {
        return $this->hasMany(\App\Models\Tanah::class, 'warga_id');
    }

    /* ================= Scopes ================= */
    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;
        return $q->where(function ($qq) use ($term) {
            $qq->where('nama_lengkap', 'like', "%{$term}%")
               ->orWhere('nik', 'like', "%{$term}%");
        });
    }

    /* ================= Accessors ================= */

    // Back-compat (kalau kamu sudah pakai foto_url sebelumnya)
    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto_ktp_url;
    }

    // URL final: http(s)://APP_URL/ktp/xxx.jpg
    public function getFotoKtpUrlAttribute(): ?string
    {
        $path = $this->attributes['foto_ktp'] ?? null;
        if (!$path) return null;

        // Sudah full URL? langsung pakai
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        // Normalisasi: buang leading slash
        $path = ltrim($path, '/');

        // Kalau yang tersimpan cuma nama file → prefix 'ktp/'
        if (!Str::startsWith($path, 'ktp/')) {
            $path = 'ktp/'.$path;
        }

        // Render absolut pakai APP_URL
        return url($path);
    }
}
