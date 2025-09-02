<?php

namespace App\GraphQL\Queries;

use App\Models\MenuOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class MenuOrderStatsQuery
{
    public function resolve($root, array $args): array
    {
        $user = Auth::user();
        $start = isset($args['start']) ? Carbon::parse($args['start']) : Carbon::now()->startOfWeek();
        $end = isset($args['end']) ? Carbon::parse($args['end']) : Carbon::now()->endOfWeek();

        $count = MenuOrder::whereHas('menu', function ($q) use ($user) {
            $q->where('company_id', $user->company_id);
        })
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        return ['count' => $count];
    }
}
