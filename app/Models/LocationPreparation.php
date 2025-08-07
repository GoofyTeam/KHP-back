<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class LocationPreparation extends Pivot
{
    protected $table = 'location_preparation';

    protected $fillable = [
        'preparation_id',
        'location_id',
        'quantity',
    ];

    public function preparation()
    {
        return $this->belongsTo(Preparation::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
