<?php

namespace App\Models;

use App\Enums\MeasurementUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MenuItem extends Model
{
    protected $fillable = [
        'menu_id',
        'entity_type',
        'entity_id',
        'quantity',
        'unit',
    ];

    protected $casts = [
        'unit' => MeasurementUnit::class,
    ];

    /**
     * L’entité associée (Ingredient ou Preparation)
     */
    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }

    /**
     * Le menu parent
     */
    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }
}
