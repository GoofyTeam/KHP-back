<?php

namespace App\Models;

use App\Enums\StepMenuStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Menu|null $menu
 * @property int $quantity
 */
class StepMenu extends Model
{
    /** @use HasFactory<\Database\Factories\StepMenuFactory> */
    use HasFactory;

    protected $fillable = [
        'order_step_id',
        'menu_id',
        'quantity',
        'status',
        'note',
        'served_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'status' => StepMenuStatus::class,
        'served_at' => 'datetime',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(OrderStep::class, 'order_step_id');
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function scopeForCompany(Builder $query): Builder
    {
        return $query->whereHas('step.order', static function (Builder $order): void {
            $order->where('company_id', auth()->user()->company_id);
        });
    }

    /**
     * @param  array<int, StepMenuStatus|string>  $statuses
     */
    public function scopeStatus(Builder $query, array $statuses): Builder
    {
        if ($statuses === []) {
            return $query;
        }

        $values = array_map(
            fn (StepMenuStatus|string $status): string => $status instanceof StepMenuStatus ? $status->value : (string) $status,
            $statuses,
        );

        return $query->whereIn('status', $values);
    }

    public function totalPrice(): float
    {
        $menu = $this->menu;

        if (! $menu) {
            return 0.0;
        }

        return (float) $menu->price * (int) $this->quantity;
    }
}
