<?php

namespace App\Models;

use App\Traits\HasStockMovements;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Preparation extends Model
{
    /** @use HasFactory<\Database\Factories\PreparationFactory> */
    use HasFactory, HasStockMovements;

    protected $guarded = [
        'id',
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

    /**
     * Search preparations by name, ignoring accents and case.
     *
     * @param  string  $search
     */
    public function scopeSearch(Builder $query, $search): Builder
    {
        if ($search) {
            return $query->whereRaw('unaccent(name) ILIKE unaccent(?)', ["%{$search}%"]);
        }

        return $query;
    }

    public function scopeCategoryId($query, $categoryId)
    {
        return $query->whereHas('categories', function ($q) use ($categoryId) {
            $q->where('id', $categoryId);
        });
    }

    /**
     * Filtre les préparations par identifiant d'emplacement
     */
    public function scopeLocationId($query, $locationId)
    {
        return $query->whereHas('locations', function ($q) use ($locationId) {
            $q->where('id', $locationId);
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

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_preparation')
            ->withTimestamps();
    }
}
