<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class Warga extends Model
{
    use HasFactory;

    protected $table = 'warga';

    protected $fillable = [
        'nama_lengkap',
        'jenis_kelamin',
        'status_perkawinan',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'pendidikan_terakhir',
        'pekerjaan',
        'foto_ktp',
        'kewarganegaraan',
        'alamat_lengkap',
        'nik',
        'keterangan',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
    ];

    // 1 warga bisa punya banyak bidang tanah (MVP: lewat kolom warga_id di tanah)
    public function bidang()
    {
        return $this->hasMany(Tanah::class, 'warga_id');
    }

    // helper pencarian cepat
    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;
        return $q->where(function ($qq) use ($term) {
            $qq->where('nama_lengkap', 'like', "%{$term}%")
               ->orWhere('nik', 'like', "%{$term}%");
        });
    }

        public function getFotoUrlAttribute(): ?string
    {
        return $this->foto_ktp ? url($this->foto_ktp) : null;
    }

}
