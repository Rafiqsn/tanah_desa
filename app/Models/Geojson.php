<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Geojson extends Model
{
    use SoftDeletes;

    protected $table = 'geojson';

    protected $fillable = [
        'nama',
        'feature_json',   // <— pakai ini
        'srid',
        'centroid_lng',
        'centroid_lat',
        'versi',
        'parent_id',
        'properties',
    ];

    protected $casts = [
        // biarkan Laravel meng-encode/decoding otomatis
        'feature_json' => 'array',
        'properties'   => 'array',
        'centroid_lng' => 'float',
        'centroid_lat' => 'float',
        'versi'        => 'integer',
    ];

    public function bidang()
    {
        return $this->hasOne(\App\Models\Bidang::class, 'geojson_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
