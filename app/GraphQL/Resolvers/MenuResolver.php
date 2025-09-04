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
}
