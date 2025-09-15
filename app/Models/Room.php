<?php

namespace App\Models;

use App\Traits\HasSearchScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    /** @use HasFactory<\Database\Factories\RoomFactory> */
    use HasFactory, HasSearchScope;

    protected $fillable = [
        'name',
        'code',
        'company_id',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class);
    }

    public function scopeForCompany($query)
    {
        return $query->where('company_id', auth()->user()->company_id);
    }
}
