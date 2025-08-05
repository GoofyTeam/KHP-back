<?php

namespace App\Models;

use App\Traits\HasStockMovements;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ingredient extends Model
{
    /** @use HasFactory<\Database\Factories\IngredientFactory> */
    use HasFactory, HasStockMovements;

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

    /**
     * Search ingredients by name.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->whereRaw('unaccent(name) ILIKE unaccent(?)', ["%{$search}%"]);
        }

        return $query;
    }

    public function scopeLocationId($query, $locationId)
    {
        return $query->whereHas('locations', function ($q) use ($locationId) {
            $q->where('id', $locationId);
        });
    }

    public function scopeCategoryId($query, $categoryId)
    {
        return $query->whereHas('categories', function ($q) use ($categoryId) {
            $q->where('id', $categoryId);
        });
    }

    public function preparationEntities()
    {
        return $this->morphMany(PreparationEntity::class, 'entity');
    }
}
