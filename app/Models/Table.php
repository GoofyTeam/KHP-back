<?php

namespace App\Models;

use App\Traits\HasSearchScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Table extends Model
{
    /** @use HasFactory<\Database\Factories\TableFactory> */
    use HasFactory, HasSearchScope;

    protected $fillable = [
        'label',
        'seats',
        'room_id',
        'company_id',
    ];

    protected string $searchColumn = 'label';

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
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
