<?php

namespace App\Models;

use App\Traits\HasSearchScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocationType extends Model
{
    /** @use HasFactory<\Database\Factories\LocationTypeFactory> */
    use HasFactory, HasSearchScope;

    protected $guarded = [
        'id',
    ];

    /**
     * Get the company that owns the location type.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the locations for this type.
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withPivot('shelf_life_hours')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include location types for the current company.
     */
    public function scopeForCompany($query)
    {
        return $query->where('company_id', auth()->user()->company_id);
    }
}
