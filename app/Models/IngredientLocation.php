<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class IngredientLocation extends Pivot
{
    protected $table = 'ingredient_location';

    protected $fillable = [
        'ingredient_id',
        'location_id',
        'quantity',
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
