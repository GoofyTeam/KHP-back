<?php

namespace App\GraphQL\Queries;

use App\Models\Perishable;
use App\Services\PerishableService;
use Illuminate\Support\Carbon;

class PerishableQuery
{
    public function resolve(mixed $_, array $args, PerishableService $service)
    {
        $filter = $args['filter'] ?? 'ACTIVE';

        if ($filter === 'EXPIRED') {
            return Perishable::onlyTrashed()->forCompany()->get();
        }

        $perishables = Perishable::with(['ingredient.category.locationTypes', 'location'])
            ->forCompany()
            ->get();

        if ($filter === 'SOON') {
            $threshold = Carbon::now()->addHours(48);
            return $perishables->filter(fn ($p) => $service->expiration($p)->between(now(), $threshold));
        }

        return $perishables->filter(fn ($p) => $service->expiration($p)->isFuture());
    }
}
