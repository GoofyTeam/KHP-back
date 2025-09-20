<?php

namespace App\Models;

use App\Enums\OrderStepStatus;
use App\Enums\StepMenuStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read float $price Prix total TTC des menus rattachés à l'étape.
 * @property-read EloquentCollection<int, StepMenu> $stepMenus
 */
class OrderStep extends Model
{
    /** @use HasFactory<\Database\Factories\OrderStepFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
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

        /** @var EloquentCollection<int, StepMenu> $stepMenus */
        $stepMenus = $this->stepMenus;

        $total = $stepMenus->sum(static fn (StepMenu $stepMenu): float => $stepMenu->totalPrice());

        return round((float) $total, 2);
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

    public function refreshStatusFromStepMenus(): void
    {
        $this->loadMissing('stepMenus');

        if ($this->stepMenus->isEmpty()) {
            if ($this->status !== OrderStepStatus::IN_PREP) {
                $this->status = OrderStepStatus::IN_PREP;
                $this->save();
            }

            return;
        }

        $allServed = $this->stepMenus->every(
            static fn (StepMenu $stepMenu): bool => $stepMenu->status === StepMenuStatus::SERVED,
        );

        $allReadyOrServed = $this->stepMenus->every(
            static fn (StepMenu $stepMenu): bool => in_array($stepMenu->status, [StepMenuStatus::READY, StepMenuStatus::SERVED], true),
        );

        $targetStatus = OrderStepStatus::IN_PREP;

        if ($allServed) {
            $targetStatus = OrderStepStatus::SERVED;
        } elseif ($allReadyOrServed) {
            $targetStatus = OrderStepStatus::READY;
        }

        if ($targetStatus !== $this->status) {
            $this->status = $targetStatus;
            $this->save();
        }
    }
}
