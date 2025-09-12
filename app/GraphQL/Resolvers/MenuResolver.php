<?php

namespace App\GraphQL\Resolvers;

use App\Models\Menu;

class MenuResolver
{
    public function imageUrl(Menu $menu): ?string
    {
        if (! $menu->image_url) {
            return null;
        }

        return url('/api/image-proxy/'.$menu->image_url);
    }

    /**
     * Resolve menu availability for a given quantity.
     *
     * @param  array<string, mixed>  $args
     */
    public function available(Menu $menu, array $args): bool
    {
        $quantity = $args['quantity'] ?? 1;

        return $menu->hasSufficientStock($quantity);
    }
}
