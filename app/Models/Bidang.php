<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bidang extends Model
{
    use SoftDeletes;

    protected $table = 'bidang';

    protected $fillable = [
        'id','tanah_id','geojson_id','kode_bidang','luas_m2',
        'hm','hgb','hp','hgu','hpl','ma','vi','tn',
        'perumahan','perdagangan_jasa','perkantoran','industri','fasilitas_umum',
        'sawah','tegalan','perkebunan','peternakan_perikanan','hutan_belukar','hutan_lindung',
        'mutasi_tanah','tanah_kosong','lain_lain',
        'is_tkd','candidate_tkd','keterangan'
    ];

    protected $casts = [
        'is_tkd' => 'boolean',
        'candidate_tkd' => 'boolean',
        'luas_m2' => 'decimal:2',
    ];

    // relasi
    public function tanah()   { return $this->belongsTo(Tanah::class); }
    public function geojson() { return $this->belongsTo(Geojson::class, 'geojson_id'); }

    // helper total
    public function getTotalStatusHakAttribute(): float
    {
        return (float)$this->hm + $this->hgb + $this->hp + $this->hgu + $this->hpl + $this->ma + $this->vi + $this->tn;
    }

    public function getTotalPenggunaanAttribute(): float
    {
        return (float)$this->perumahan + $this->perdagangan_jasa + $this->perkantoran + $this->industri + $this->fasilitas_umum
             + $this->sawah + $this->tegalan + $this->perkebunan + $this->peternakan_perikanan
             + $this->hutan_belukar + $this->hutan_lindung + $this->mutasi_tanah + $this->tanah_kosong + $this->lain_lain;
    }
}
