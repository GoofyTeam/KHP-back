<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocationType extends Model
{
    /** @use HasFactory<\Database\Factories\LocationTypeFactory> */
    use HasFactory;

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

    /**
     * Scope a query to only include location types for the current company.
     */
    public function scopeForCompany($query)
    {
        return $query->where('company_id', auth()->user()->company_id);
    }

    /**
     * Search location types by name, ignoring accents and case.
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
