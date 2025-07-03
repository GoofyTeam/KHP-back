<?php

namespace App\Models;

use App\Enums\PreparationTypeEnum;
use App\Enums\UnitEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Preparation extends Model
{
    /** @use HasFactory<\Database\Factories\PreparationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'unit',
        'type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => UnitEnum::class,
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

    /**
     * Scope a query to only include preparations owned by a specific company.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \App\Models\Company  $company
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUserCompany($query)
    {
        $user = Auth::user();

        // Si votre User a bien un champ company_id
        return $query->where('company_id', $user->company_id);
    }
}
