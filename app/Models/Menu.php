<?php

namespace App\Models;

use App\Enums\MenuServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'menu_type_id',
        'service_type',
        'is_returnable',
        'public_priority',
        'price',
    ];

    protected $casts = [
        'is_a_la_carte' => 'boolean',
        'menu_type_id' => 'integer',
        'service_type' => MenuServiceType::class,
        'is_returnable' => 'boolean',
        'price' => 'float',
        'public_priority' => 'integer',
    ];

    protected $appends = ['type'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(MenuCategory::class, 'menu_category_menu');
    }

    public function menuType(): BelongsTo
    {
        return $this->belongsTo(MenuType::class);
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

    public function scopeCategory($query, $categoryIds)
    {
        if (empty($categoryIds)) {
            return $query;
        }

        $categoryIds = is_array($categoryIds) ? $categoryIds : [$categoryIds];

        return $query->whereHas('categories', function ($q) use ($categoryIds) {
            $q->whereIn('menu_categories.id', $categoryIds);
        });
    }

    public function scopeType($query, $types)
    {
        if (empty($types)) {
            return $query;
        }

        $types = is_array($types) ? $types : [$types];

        $ids = [];
        $names = [];

        foreach ($types as $type) {
            if (is_numeric($type)) {
                $ids[] = (int) $type;
            } else {
                $names[] = $type;
            }
        }

        return $query->whereHas('menuType', function ($q) use ($ids, $names) {
            $q->where(function ($inner) use ($ids, $names) {
                if (! empty($names)) {
                    $inner->whereIn('name', $names);
                }

                if (! empty($ids)) {
                    if (! empty($names)) {
                        $inner->orWhereIn('id', $ids);
                    } else {
                        $inner->whereIn('id', $ids);
                    }
                }
            });
        });
    }

    public function getTypeAttribute(): ?string
    {
        $menuType = $this->getRelationValue('menuType');

        return $menuType?->name;
    }

    public function scopeServiceType($query, $serviceTypes)
    {
        if (empty($serviceTypes)) {
            return $query;
        }

        $serviceTypes = is_array($serviceTypes) ? $serviceTypes : [$serviceTypes];

        $serviceTypes = array_map(
            fn ($serviceType) => $serviceType instanceof MenuServiceType ? $serviceType->value : $serviceType,
            $serviceTypes
        );

        return $query->whereIn('service_type', $serviceTypes);
    }

    public function scopePriceBetween($query, $prices)
    {
        if (! is_array($prices) || count($prices) !== 2) {
            return $query;
        }

        [$min, $max] = $prices;

        return $query->whereBetween('price', [$min, $max]);
    }

    public function scopeAvailable($query, ?bool $available)
    {
        if ($available === null) {
            return $query;
        }

        $method = $available ? 'whereDoesntHave' : 'whereHas';

        return $query->$method('items', function ($itemQuery) {
            $itemQuery->where(function ($q) {
                $q->where(function ($ingredientSub) {
                    $ingredientSub->where('entity_type', Ingredient::class)
                        ->whereRaw(
                            'COALESCE((SELECT quantity FROM ingredient_location WHERE ingredient_id = menu_items.entity_id AND location_id = menu_items.location_id), 0) < menu_items.quantity'
                        );
                })->orWhere(function ($prepSub) {
                    $prepSub->where('entity_type', Preparation::class)
                        ->whereRaw(
                            'COALESCE((SELECT quantity FROM location_preparation WHERE preparation_id = menu_items.entity_id AND location_id = menu_items.location_id), 0) < menu_items.quantity'
                        );
                });
            });
        });
    }

    public function scopeForCompany($query)
    {
        return $query->where('company_id', auth()->user()->company_id);
    }

    public function hasSufficientStock(int $quantity = 1): bool
    {
        $this->loadMissing('items');
        $converter = app(\App\Services\UnitConversionService::class);
        foreach ($this->items as $item) {
            $entityClass = $item->entity_type;
            $entity = $entityClass::find($item->entity_id);
            if (! $entity) {
                return false;
            }
            $available = (float) ($entity->locations()->find($item->location_id)?->pivot->quantity ?? 0);
            $required = $converter->convert($item->quantity * $quantity, $item->unit, $entity->unit);
            if ($available < $required) {
                return false;
            }
        }

        return true;
    }
}
