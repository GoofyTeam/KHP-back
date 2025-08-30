<?php

namespace App\Models;

use App\Services\PerishableService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Perishable extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ingredient_id',
        'location_id',
        'company_id',
        'quantity',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany(Builder $query): Builder
    {
        return $query->where('company_id', auth()->user()->company_id);
    }

    public function getExpirationAtAttribute(): CarbonInterface
    {
        return app(PerishableService::class)->expiration($this);
    }
}
