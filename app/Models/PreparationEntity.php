<?php

namespace App\Models;

use App\Enums\MeasurementUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \Illuminate\Database\Eloquent\Model|null $entity
 */
class PreparationEntity extends Model
{
    protected $fillable = [
        'preparation_id',
        'entity_id',
        'entity_type',
        'location_id',
        'quantity',
        'unit',
    ];

    protected $casts = [
        'unit' => MeasurementUnit::class,
    ];

    public function entity()
    {
        return $this->morphTo();
    }

    public function preparation()
    {
        return $this->belongsTo(Preparation::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
