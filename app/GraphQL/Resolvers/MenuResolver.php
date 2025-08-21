<?php

namespace App\GraphQL\Resolvers;

use App\Models\Menu;
use App\Models\MenuItem;

class MenuResolver
{
    /**
     * Récupère les éléments d’un menu avec leurs détails
     */
    public function items(Menu $menu): array
    {
        $items = [];

        /** @var MenuItem $item */
        foreach ($menu->items as $item) {
            $items[] = [
                'entity' => $item->entity,  // Ingredient ou Preparation
                'quantity' => $item->quantity,
                'unit' => $item->unit->value,
            ];
        }

        return $items;
    }
}
