<?php

namespace App\Models;

use App\Enums\OrderStepStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read float $price
 */
class OrderStep extends Model
{
    /** @use HasFactory<\Database\Factories\OrderStepFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'name',
        'position',
        'status',
        'served_at',
    ];

    protected $casts = [
        'position' => 'integer',
        'status' => OrderStepStatus::class,
        'served_at' => 'datetime',
    ];

    protected $appends = [
        'price',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function stepMenus(): HasMany
    {
        return $this->hasMany(StepMenu::class);
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'step_menus', 'order_step_id', 'menu_id')
            ->withPivot('quantity', 'status', 'note', 'served_at')
            ->withTimestamps();
    }

    public function getPriceAttribute(): float
    {
        $this->loadMissing('stepMenus.menu');

        $total = 0.0;

        foreach ($this->stepMenus as $stepMenu) {
            /** @var StepMenu $stepMenu */
            $menu = $stepMenu->menu;

            if (! $menu) {
                continue;
            }

            $total += (float) $menu->price * (int) $stepMenu->quantity;
        }

        return $total;
    }

    public function scopeForCompany(Builder $query): Builder
    {
        return $query->whereHas('order', static function (Builder $order): void {
            $order->where('company_id', auth()->user()->company_id);
        });
    }

    /**
     * @param  array<int, OrderStepStatus|string>  $statuses
     */
    public function scopeStatus(Builder $query, array $statuses): Builder
    {
        if ($statuses === []) {
            return $query;
        }

        $values = array_map(
            fn (OrderStepStatus|string $status): string => $status instanceof OrderStepStatus ? $status->value : (string) $status,
            $statuses,
        );

        return $query->whereIn('status', $values);
    }
}
