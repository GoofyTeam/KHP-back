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

        if ($filter === 'EXPIRED') {
            return Perishable::onlyTrashed()->forCompany()->get();
        }

        $perishables = Perishable::with(['ingredient.category.locationTypes', 'location'])
            ->forCompany()
            ->get();

        $threshold = Carbon::now()->addHours(48);

        if ($filter === 'SOON') {
            return $perishables->filter(fn ($p) => $this->service->expiration($p)->between(now(), $threshold));
        }

        return $perishables->filter(fn ($p) => $this->service->expiration($p)->greaterThan($threshold));
    }
}
