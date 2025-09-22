<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $day_of_week
 * @property string $opens_at
 * @property string $closes_at
 * @property bool $is_overnight
 * @property int $sequence
 * @property-read \App\Models\Company $company
 */
class CompanyBusinessHour extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'day_of_week',
        'opens_at',
        'closes_at',
        'is_overnight',
        'sequence',
    ];

    protected $casts = [
        'company_id' => 'int',
        'day_of_week' => 'int',
        'opens_at' => 'string',
        'closes_at' => 'string',
        'is_overnight' => 'bool',
        'sequence' => 'int',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
