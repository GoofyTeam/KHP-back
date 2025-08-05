<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'category_ingredient')
            ->withTimestamps();
    }

    public function preparations(): BelongsToMany
    {
        return $this->belongsToMany(Preparation::class, 'category_preparation')
            ->withTimestamps();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany($query)
    {
        return $query->where('company_id', auth()->user()->company_id);
    }

    /**
     * Search categories by name, ignoring accents and case.
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
}
