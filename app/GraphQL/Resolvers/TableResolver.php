<?php

namespace App\GraphQL\Resolvers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Table;
use Illuminate\Database\Eloquent\Collection;

class TableResolver
{
    /**
     * @return Collection<int, Order>|null
     */
    public function orders(Table $table): ?Collection
    {
        /** @var Collection<int, Order> $orders */
        $orders = $table->orders()
            ->whereIn('status', [
                OrderStatus::PENDING->value,
                OrderStatus::SERVED->value,
            ])
            ->latest('created_at')
            ->get();

        return $orders->isEmpty() ? null : $orders;
    }
}
