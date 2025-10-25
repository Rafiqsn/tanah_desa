<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tanah extends Model
{
    use HasFactory;

    protected $table = 'tanah';

    protected $fillable = [
        'nomor_urut',
        'nama_pemilik_text',
        'warga_id',
        'jumlah_m2',

        // status hak (bersertifikat)
        'hm','hgb','hp','hgu','hpl',
        // status hak (belum)
        'ma','vi','tn',

        // penggunaan non-pertanian
        'perumahan','perdagangan_jasa','perkantoran','industri','fasilitas_umum',
        // penggunaan pertanian
        'sawah','tegalan','perkebunan','peternakan_perikanan','hutan_belukar','hutan_lindung',

        // lain-lain
        'mutasi','tanah_kosong','lain_lain','keterangan',
    ];

    protected $casts = [
        'jumlah_m2' => 'decimal:2',

        // status hak
        'hm' => 'decimal:2', 'hgb' => 'decimal:2', 'hp' => 'decimal:2',
        'hgu' => 'decimal:2', 'hpl' => 'decimal:2',
        'ma' => 'decimal:2', 'vi' => 'decimal:2', 'tn' => 'decimal:2',

        // penggunaan
        'perumahan' => 'decimal:2', 'perdagangan_jasa' => 'decimal:2',
        'perkantoran' => 'decimal:2', 'industri' => 'decimal:2',
        'fasilitas_umum' => 'decimal:2',
        'sawah' => 'decimal:2', 'tegalan' => 'decimal:2',
        'perkebunan' => 'decimal:2', 'peternakan_perikanan' => 'decimal:2',
        'hutan_belukar' => 'decimal:2', 'hutan_lindung' => 'decimal:2',

        'tanah_kosong' => 'decimal:2', 'lain_lain' => 'decimal:2',
    ];

    /* ================== RELATIONSHIPS ================== */

    // pemilik utama (MVP)
    public function pemilik()
    {
        return $this->belongsTo(Warga::class, 'warga_id');
    }

    // geojson 1:1
    public function geojson()
    {
        return $this->hasOne(Geojson::class);
    }

    // sisa luas (cek validasi)
    public function getSisaLuasAttribute(): float
    {
        return (float)($this->jumlah_m2 - $this->total_penggunaan);
    }

    /* ================== SCOPES ================== */

    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;
        return $q->where(function ($qq) use ($term) {
            $qq->where('nomor_urut', 'like', "%{$term}%")
               ->orWhere('nama_pemilik_text', 'like', "%{$term}%");
        });
    }

    public function scopeKategoriHak($q, string $kategori) // 'bersertifikat' | 'belum'
    {
        if ($kategori === 'bersertifikat') {
            return $q->whereRaw('(hm+hgb+hp+hgu+hpl) > 0');
        }
        if ($kategori === 'belum') {
            return $q->whereRaw('(ma+vi+tn) > 0');
        }
        return $q;
    }

        public function bidang()
    {
        return $this->hasMany(\App\Models\Bidang::class, 'tanah_id');
    }

    public function recalcJumlahM2(bool $save = true): void
    {
        $sum = (float) $this->bidang()->sum('luas_m2');
        $this->jumlah_m2 = $sum;
        if ($save) $this->save();
    }

    // optional: expose nilai hitung langsung di JSON
    protected $appends = ['jumlah_m2_computed'];
    public function getJumlahM2ComputedAttribute(): float
    {
        return (float) $this->bidang()->sum('luas_m2');
    }

}
