<?php

namespace App\Models;

use App\Enums\MeasurementUnit;
use App\Traits\HasLosses;
use App\Traits\HasSearchScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ingredient extends Model
{
    /** @use HasFactory<\Database\Factories\IngredientFactory> */
    use HasFactory, HasLosses, HasSearchScope;

    protected $fillable = [
        'name',
        'company_id',
        'category_id',
        'image_url',
        'unit',
        'base_quantity',
        'barcode',
        'base_unit',
        'allergens',
    ];

    protected $casts = [
        'unit' => MeasurementUnit::class,
        'base_unit' => MeasurementUnit::class,
        'allergens' => 'array',
    ];

    protected $attributes = [
        'allergens' => '[]',
    ];

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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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

        if (is_array($categoryIds)) {
            return $query->whereIn('category_id', $categoryIds);
        }

        return $query->where('category_id', $categoryIds);
    }

    /**
     * Filtre par code-barres exact.
     */
    public function scopeBarcode($query, ?string $barcode)
    {
        if (empty($barcode)) {
            return $query;
        }

        return $query->where('barcode', $barcode);
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
