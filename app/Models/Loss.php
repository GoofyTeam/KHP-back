<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Loss extends Model
{
    protected $fillable = [
        'entity_type',   // Classname : App\Models\Ingredient ou App\Models\Preparation
        'entity_id',     // ID de l'entité
        'location_id',   // ID de la localisation
        'quantity',      // Quantité perdue
        'reason',        // Raison de la perte
    ];

    /**
     * Polymorphic relation to Ingredient or Preparation
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Relation vers la localisation
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
