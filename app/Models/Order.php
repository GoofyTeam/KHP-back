<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'table_id',
        'company_id',
        'user_id',
        'status',
        'pending_at',
        'in_prep_at',
        'ready_at',
        'served_at',
        'payed_at',
        'canceled_at',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'pending_at' => 'datetime',
        'in_prep_at' => 'datetime',
        'ready_at' => 'datetime',
        'served_at' => 'datetime',
        'payed_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    // Relations (adapter les classes si vos modèles diffèrent)
    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
