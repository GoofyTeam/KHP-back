<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $company_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MenuItem> $items
 */
class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'image_url',
        'is_a_la_carte',
        'is_available',
    ];

    protected $casts = [
        'is_a_la_carte' => 'boolean',
        'is_available' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MenuOrder::class);
    }

    public function getAllergensAttribute(): array
    {
        $this->loadMissing('items.entity');

        return $this->items
            ->pluck('entity')
            ->filter(fn ($entity) => $entity && isset($entity->allergens))
            ->flatMap(fn ($entity) => $entity->allergens)
            ->unique()
            ->values()
            ->all();
    }

    public function scopeAllergen($query, $allergens)
    {
        if (empty($allergens)) {
            return $query;
        }

        $allergens = is_array($allergens) ? $allergens : [$allergens];

        return $query->where(function ($q) use ($allergens) {
            foreach ($allergens as $allergen) {
                $q->orWhereHas('items', function ($itemQuery) use ($allergen) {
                    $itemQuery->where(function ($sub) use ($allergen) {
                        $sub->whereHasMorph('entity', [Ingredient::class], function ($ingredientQuery) use ($allergen) {
                            $ingredientQuery->whereJsonContains('allergens', $allergen);
                        })->orWhereHasMorph('entity', [Preparation::class], function ($prepQuery) use ($allergen) {
                            $prepQuery->whereHas('entities', function ($inner) use ($allergen) {
                                $inner->whereHasMorph('entity', [Ingredient::class], function ($ingredientQuery) use ($allergen) {
                                    $ingredientQuery->whereJsonContains('allergens', $allergen);
                                });
                            });
                        });
                    });
                });
            }
        });
    }

    public function scopeForCompany($query)
    {
        return $query->where('company_id', auth()->user()->company_id);
    }

    public function hasSufficientStock(int $quantity = 1): bool
    {
        $this->loadMissing('items');
        foreach ($this->items as $item) {
            $entityClass = $item->entity_type;
            $entity = $entityClass::find($item->entity_id);
            if (! $entity) {
                return false;
            }
            $available = (float) ($entity->locations()->find($item->location_id)?->pivot->quantity ?? 0);
            if ($available < $item->quantity * $quantity) {
                return false;
            }
        }

        return true;
    }

    public function refreshAvailability(): void
    {
        $this->is_available = $this->hasSufficientStock();
        $this->save();
    }
}
