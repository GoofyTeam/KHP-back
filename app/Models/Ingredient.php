<?php

namespace App\Models;

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
    use HasFactory, HasSearchScope, HasStockMovements;

    protected $guarded = [
        'id',
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

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_ingredient')
            ->withTimestamps();
    }

    public function scopeForCompany($query)
    {
        return $query->where('company_id', auth()->user()->company_id);
    }

    public function scopeLocationId($query, $locationId)
    {
        return $query->whereHas('locations', function ($q) use ($locationId) {
            $q->where('locations.id', $locationId);
        });
    }

    public function scopeCategoryId($query, $categoryId)
    {
        return $query->whereHas('categories', function ($q) use ($categoryId) {
            $q->where('categories.id', $categoryId);
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
