<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $company_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MenuItem> $items
 */
class Menu extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MenuOrder::class);
    }

    public function scopeForCompany($query)
    {
        return $query->where('company_id', auth()->user()->company_id);
    }
}
