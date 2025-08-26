<?php

namespace App\Models;

use App\Traits\HasSearchScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory, HasSearchScope;

    protected $guarded = [
        'id',
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

    public function scopeForCompany($query)
    {
        return $query->where('company_id', auth()->user()->company_id);
    }
}
