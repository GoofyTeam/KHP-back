<?php

namespace App\Models;

use App\Traits\HasSearchScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read \Illuminate\Database\Eloquent\Relations\Pivot $pivot
 * @property-read float $quantity
 * @property int $id
 * @property string $name
 * @property int $company_id
 * @property int|null $location_type_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\LocationType|null $locationType
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ingredient> $ingredients
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LocationType> $locationTypes
 */
class Location extends Model
{
    /** @use HasFactory<\Database\Factories\LocationFactory> */
    use HasFactory, HasSearchScope;

    protected $fillable = [
        'name',
        'company_id',
        'location_type_id',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the location type for this location.
     */
    public function locationType(): BelongsTo
    {
        return $this->belongsTo(LocationType::class);
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class);
    }

    public function preparations(): BelongsToMany
    {
        return $this->belongsToMany(Preparation::class);
    }

    public function getQuantityAttribute(): float
    {
        /**
         * @var \Illuminate\Database\Eloquent\Relations\Pivot&object{quantity: float} $pivot
         */
        $pivot = $this->pivot;

        return $pivot->quantity;
    }

    public function scopeForCompany($query)
    {
        return $query->where('company_id', auth()->user()->company_id);
    }
}
