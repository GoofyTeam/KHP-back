<?php

namespace App\Models;

use App\Enums\PreparationTypeEnum;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Preparation extends Model
{
    /** @use HasFactory<\Database\Factories\PreparationFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'unit',
        'type',
        'company_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PreparationTypeEnum::class,
        ];
    }

    /**
     * Get the company that owns the preparation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany(Builder $q): Builder
    {
        return $q->where('company_id', auth()->user()->company_id);
    }
}
