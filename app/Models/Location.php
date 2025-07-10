<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read \Illuminate\Database\Eloquent\Relations\Pivot $pivot
 */
class Location extends Model
{
    /** @use HasFactory<\Database\Factories\LocationFactory> */
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class);
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
