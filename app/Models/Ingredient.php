<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ingredient extends Model
{
    /** @use HasFactory<\Database\Factories\IngredientFactory> */
    use HasFactory;

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

    /**
     * Filter ingredients by a location’s name (LIKE %…%).
     */
    public function scopeLocationName($query, $locationName)
    {
        return $query->whereHas('locations', function ($q) use ($locationName) {
            $q->where('name', 'like', "%{$locationName}%");
        });
    }

    public function preparationEntities()
    {
        return $this->morphMany(PreparationEntity::class, 'entity');
    }
}
