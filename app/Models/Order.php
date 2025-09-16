<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property-read float $price
 */
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

    protected $appends = [
        'price',
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

    public function steps(): HasMany
    {
        return $this->hasMany(OrderStep::class);
    }

    public function menus(): HasManyThrough
    {
        return $this->hasManyThrough(
            StepMenu::class,
            OrderStep::class,
            'order_id',
            'order_step_id',
            'id',
            'id'
        )->with('menu');
    }

    public function getPriceAttribute(): float
    {
        $this->loadMissing('steps.stepMenus.menu');

        $total = 0.0;

        foreach ($this->steps as $step) {
            /** @var OrderStep $step */
            $total += (float) $step->price;
        }

        return $total;
    }
}
