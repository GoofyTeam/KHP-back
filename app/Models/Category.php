<?php

namespace App\Models;

use App\Traits\HasSearchScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory, HasSearchScope;

    protected $fillable = [
        'name',
        'company_id',
    ];

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }

    public function preparations(): HasMany
    {
        return $this->hasMany(Preparation::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function locationTypes(): BelongsToMany
    {
        return $this->belongsToMany(LocationType::class)
            ->withPivot('shelf_life_hours')
            ->withTimestamps();
    }

    public function scopeForCompany($query)
    {
        return $query->where('company_id', auth()->user()->company_id);
    }
}
