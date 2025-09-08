<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * @property int $id
 * @property int $location_id
 * @property int $company_id
 * @property int $user_id
 * @property float $quantity
 * @property string|null $reason
 * @property-read Ingredient|Preparation|null $lossable
 * @property-read Location|null $location
 */
class Loss extends Model
{
    use HasFactory;

    protected $fillable = [
        'loss_item_id',
        'loss_item_type',
        'location_id',
        'company_id',
        'user_id',
        'quantity',
        'reason',
    ];

    public function lossable(): MorphTo
    {
        return $this->morphTo('loss_item');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForCompany($query)
    {
        return $query->where('company_id', auth()->user()->company_id);
    }

    public function cancel(): void
    {
        /** @var Ingredient|Preparation|null $trackable */
        $trackable = $this->lossable;
        if ($trackable === null) {
            throw new RuntimeException('Missing lossable relation');
        }

        $location = $this->location;
        if ($location === null) {
            throw new RuntimeException('Missing location relation');
        }

        // Sécurité logique d’appartenance
        if ($trackable->company_id !== $this->company_id || $location->company_id !== $this->company_id) {
            throw new RuntimeException('Loss does not belong to this company');
        }

        $locationEntity = $trackable->locations()->where('locations.id', $location->id)->first();
        if (! $locationEntity) {
            throw new RuntimeException('Trackable not stored at this location');
        }

        /** @var \Illuminate\Database\Eloquent\Relations\Pivot&object{quantity: float} $pivot */
        $pivot = $locationEntity->pivot;
        $before = (float) $pivot->quantity;
        $after = round($before + $this->quantity, 2);

        $trackable->locations()->updateExistingPivot($location->id, ['quantity' => $after]);

        $trackable->recordStockMovement($location, $before, $after, 'Loss Rollback');

        $this->delete();
    }
}
