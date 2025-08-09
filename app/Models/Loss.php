<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loss extends Model
{
    protected $table = 'losses';

    protected $fillable = [
        'company_id',
        'ingredient_id',
        'ingredient_type',
        'location_id',
        'quantity',
        'unit',
        'reason',
        'comment',
    ];

    public function ingredient()
    {
        return $this->morphTo();
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
