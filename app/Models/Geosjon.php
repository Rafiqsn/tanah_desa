<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Geojson extends Model
{
    protected $table = 'geojson';

    protected $fillable = [
        'tanah_id',
        'feature',
        'centroid_lat',
        'centroid_lng',
        'luas_terhitung_m2',
    ];

    protected $casts = [
        'feature'           => 'array',   // otomatis decode JSON → array
        'centroid_lat'      => 'float',
        'centroid_lng'      => 'float',
        'luas_terhitung_m2' => 'decimal:2',
    ];

    public function tanah()
    {
        return $this->belongsTo(Tanah::class);
    }
}
