<?php

namespace App\GraphQL\Resolvers;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\Carbon;

class OrdersResolver
{
    /**
     * @param  array<string, mixed>  $args
     * @return array<string, float|int>
     */
    public function stats(null $_, array $args): array
    {
        $start = isset($args['start_date']) ? Carbon::parse($args['start_date']) : null;
        $end = isset($args['end_date']) ? Carbon::parse($args['end_date']) : null;

        $query = Order::query()->forCompany();

        if (isset($args['table_id'])) {
            $query->where('table_id', $args['table_id']);
        }

        if (isset($args['user_id'])) {
            $query->where('user_id', $args['user_id']);
        }

        if (isset($args['statuses']) && $args['statuses'] !== []) {
            $query->status($args['statuses']);
        }

        if ($start && $end) {
            $query->whereBetween('created_at', [$start, $end]);
        } elseif ($start) {
            $query->where('created_at', '>=', $start);
        } elseif ($end) {
            $query->where('created_at', '<=', $end);
        }

        $baseQuery = clone $query;

        $counts = [];
        foreach (OrderStatus::cases() as $status) {
            $counts[$status->value] = (clone $baseQuery)
                ->where('status', $status->value)
                ->count();
        }

        $revenue = (clone $baseQuery)
            ->where('status', OrderStatus::PAYED->value)
            ->with('steps.stepMenus.menu')
            ->get()
            ->sum(static fn (Order $order): float => $order->price);

        return [
            'pending' => (int) ($counts[OrderStatus::PENDING->value] ?? 0),
            'in_prep' => (int) ($counts[OrderStatus::IN_PREP->value] ?? 0),
            'ready' => (int) ($counts[OrderStatus::READY->value] ?? 0),
            'served' => (int) ($counts[OrderStatus::SERVED->value] ?? 0),
            'payed' => (int) ($counts[OrderStatus::PAYED->value] ?? 0),
            'cancelled' => (int) ($counts[OrderStatus::CANCELLED->value] ?? 0),
            'total' => (int) array_sum($counts),
            'revenue' => (float) $revenue,
        ];
    }
}
