<?php

namespace App\GraphQL\Queries;

use App\Models\Perishable;
use App\Services\PerishableService;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Carbon;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class PerishableQuery
{
    public function __construct(private PerishableService $service) {}

    public function resolve(mixed $_, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $filter = $args['filter'] ?? 'FRESH';
        $hasIsReadFilter = array_key_exists('is_read', $args);
        $isRead = $hasIsReadFilter ? (bool) $args['is_read'] : null;

        if ($filter === 'EXPIRED') {
            $query = Perishable::onlyTrashed()->forCompany();

            if ($hasIsReadFilter) {
                $query->where('is_read', $isRead);
            }

            return $query->get();
        }

        $query = Perishable::with(['ingredient.category.locationTypes', 'location'])
            ->forCompany();

        if ($hasIsReadFilter) {
            $query->where('is_read', $isRead);
        }

        $perishables = $query->get();

        $threshold = Carbon::now()->addHours(48);

        if ($filter === 'SOON') {
            return $perishables->filter(fn ($p) => $this->service->expiration($p)->between(now(), $threshold));
        }

        return $perishables->filter(fn ($p) => $this->service->expiration($p)->greaterThan($threshold));
    }
}
