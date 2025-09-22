<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderStepStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property-read float $price Prix total TTC calculé à partir des menus des étapes.
 * @property-read EloquentCollection<int, OrderStep> $steps
 * @property-read EloquentCollection<int, StepMenu> $menus
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
        'served_at',
        'payed_at',
        'canceled_at',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'pending_at' => 'datetime',
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

    public function histories(): HasMany
    {
        return $this->hasMany(OrderHistory::class)->orderBy('created_at');
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

        /** @var EloquentCollection<int, OrderStep> $steps */
        $steps = $this->steps;

        $total = $steps->sum(static function (OrderStep $step): float {
            /** @var EloquentCollection<int, StepMenu> $stepMenus */
            $stepMenus = $step->stepMenus;

            return $stepMenus->sum(static fn (StepMenu $stepMenu): float => $stepMenu->totalPrice());
        });

        return round((float) $total, 2);
    }

    public function scopeForCompany(Builder $query): Builder
    {
        return $query->where('company_id', auth()->user()->company_id);
    }

    /**
     * @param  array<int, OrderStatus|string>  $statuses
     */
    public function scopeStatus(Builder $query, array $statuses): Builder
    {
        if ($statuses === []) {
            return $query;
        }

        $values = array_map(
            fn (OrderStatus|string $status): string => $status instanceof OrderStatus ? $status->value : (string) $status,
            $statuses,
        );

        return $query->whereIn('status', $values);
    }

    public function scopeCreatedAfter(Builder $query, mixed $date): Builder
    {
        if ($date === null) {
            return $query;
        }

        $moment = $date instanceof CarbonInterface ? $date : Carbon::parse((string) $date);

        return $query->where('created_at', '>=', $moment);
    }

    public function scopeCreatedBefore(Builder $query, mixed $date): Builder
    {
        if ($date === null) {
            return $query;
        }

        $moment = $date instanceof CarbonInterface ? $date : Carbon::parse((string) $date);

        return $query->where('created_at', '<=', $moment);
    }

    public function refreshStatusFromSteps(): bool
    {
        if (in_array($this->status, [OrderStatus::PAYED, OrderStatus::CANCELED], true)) {
            return false;
        }

        $this->loadMissing('steps');

        /** @var EloquentCollection<int, OrderStep> $steps */
        $steps = $this->steps;

        $targetStatus = OrderStatus::PENDING;

        if ($steps->isNotEmpty() && $steps->every(
            static fn (OrderStep $step): bool => $step->status === OrderStepStatus::SERVED,
        )) {
            $targetStatus = OrderStatus::SERVED;
        }

        if ($targetStatus === $this->status) {
            $dirty = false;

            if ($targetStatus === OrderStatus::SERVED) {
                if ($this->served_at === null) {
                    $this->served_at = now();
                    $dirty = true;
                }
            } elseif ($this->served_at !== null) {
                $this->served_at = null;
                $dirty = true;
            }

            if ($dirty) {
                $this->save();
            }

            return false;
        }

        $this->status = $targetStatus;

        if ($targetStatus === OrderStatus::SERVED) {
            $this->served_at = now();
        } else {
            $this->served_at = null;
        }

        $this->save();

        return true;
    }
}
