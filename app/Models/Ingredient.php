<?php

namespace App\Models;

use App\Enums\MeasurementUnit;
use App\Traits\HasLosses;
use App\Traits\HasSearchScope;
use App\Traits\HasStockMovements;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ingredient extends Model
{
    /** @use HasFactory<\Database\Factories\IngredientFactory> */
    use HasFactory, HasLosses, HasSearchScope, HasStockMovements;

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'unit' => MeasurementUnit::class,
    ];

    protected static function booted()
    {
        static::created(function (Ingredient $ingredient) {
            $locations = Location::where('company_id', $ingredient->company_id)->pluck('id');

            $syncData = $locations
                ->mapWithKeys(fn ($id) => [$id => ['quantity' => 0]])
                ->toArray();

            if (! empty($syncData)) {
                $ingredient->locations()->syncWithoutDetaching($syncData);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class)
            ->using(IngredientLocation::class)
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_ingredient')
            ->withTimestamps();
    }

    public function scopeForCompany($query)
    {
        return $query->where('company_id', auth()->user()->company_id);
    }

    public function scopeLocationId($query, $locationIds)
    {
        if (empty($locationIds)) {
            return $query;
        }

        return $query->whereHas('locations', function ($q) use ($locationIds) {
            if (is_array($locationIds)) {
                $q->whereIn('locations.id', $locationIds);
            } else {
                $q->where('locations.id', $locationIds);
            }
        });
    }

    public function scopeCategoryId($query, $categoryIds)
    {
        if (empty($categoryIds)) {
            return $query;
        }

        return $query->whereHas('categories', function ($q) use ($categoryIds) {
            if (is_array($categoryIds)) {
                $q->whereIn('categories.id', $categoryIds);
            } else {
                $q->where('categories.id', $categoryIds);
            }
        });
    }

    public function preparationEntities()
    {
        return $this->morphMany(PreparationEntity::class, 'entity');
    }

    /**
     * Injecte trois alias de compteurs de retraits pour tri et affichage.
     */
    public function scopeWithWithdrawalCounts($query)
    {
        $now = CarbonImmutable::now();

        return $query->withCount([
            'stockMovements as withdrawals_today_count' => function ($q) use ($now) {
                $q->where('type', 'withdrawal')
                    ->whereDate('created_at', $now->toDateString());
            },
            'stockMovements as withdrawals_this_week_count' => function ($q) use ($now) {
                $q->where('type', 'withdrawal')
                    ->whereBetween('created_at', [
                        $now->startOfWeek()->toDateTimeString(),
                        $now->endOfWeek()->toDateTimeString(),
                    ]);
            },
            'stockMovements as withdrawals_this_month_count' => function ($q) use ($now) {
                $q->where('type', 'withdrawal')
                    ->whereMonth('created_at', $now->month)
                    ->whereYear('created_at', $now->year);
            },
        ]);
    }
}
