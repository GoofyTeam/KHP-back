<?php

namespace App\Models;

use App\Enums\MeasurementUnit;
use App\Traits\HasLosses;
use App\Traits\HasSearchScope;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Preparation extends Model
{
    /** @use HasFactory<\Database\Factories\PreparationFactory> */
    use HasFactory, HasLosses, HasSearchScope;

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'unit' => MeasurementUnit::class,
    ];

    /**
     * Get the company that owns the preparation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get entities related to the preparation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function entities()
    {
        return $this->hasMany(PreparationEntity::class);
    }

    public function scopeForCompany(Builder $q): Builder
    {
        return $q->where('company_id', auth()->user()->company_id);
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
     * Filtre les préparations par identifiants d'emplacements (un ou plusieurs)
     */
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

    public function preparationEntities()
    {
        return $this->morphMany(PreparationEntity::class, 'entity');
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class)
            ->using(LocationPreparation::class)
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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
