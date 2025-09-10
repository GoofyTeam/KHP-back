<?php

namespace App\Models;

use App\Traits\HasSearchScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MenuCategory extends Model
{
    /** @use HasFactory<\Database\Factories\MenuCategoryFactory> */
    use HasFactory, HasSearchScope;

    protected $fillable = [
        'name',
        'company_id',
    ];

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany($query)
    {
        return $query->where('company_id', auth()->user()->company_id);
    }
}
