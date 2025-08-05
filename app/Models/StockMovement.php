<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'trackable_id',
        'trackable_type',
        'location_id',
        'company_id',
        'user_id',
        'type',
        'quantity',
        'quantity_before',
        'quantity_after',
    ];

    /**
     * Obtenir l'entité liée au mouvement (ingrédient ou préparation)
     */
    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Obtenir l'emplacement associé au mouvement
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Obtenir l'entreprise associée au mouvement
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Obtenir l'utilisateur qui a effectué l'opération
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Limiter les requêtes à l'entreprise actuelle
     */
    public function scopeForCompany($query)
    {
        return $query->where('company_id', auth()->user()->company_id);
    }
}
