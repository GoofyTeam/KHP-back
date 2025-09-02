<?php

namespace App\GraphQL\Resolvers;

use App\Models\Ingredient;

class IngredientResolver
{
    /**
     * Generate a temporary S3 URL for the ingredient image.
     */
    public function imageUrl(Ingredient $ingredient): ?string
    {
        if (! $ingredient->image_url) {
            return null;
        }

        return url('/api/image-proxy/'.$ingredient->image_url);
    }

    public function quantityByLocation(Ingredient $ingredient): array
    {
        $quantities = [];

        /** @var \App\Models\Location $location */
        foreach ($ingredient->locations as $location) {
            $quantities[] = [
                'quantity' => $location->getQuantityAttribute(),
                'location' => $location,
            ];
        }

        return $quantities;
    }

    /**
     * Retrieve stock movements for an ingredient with optional ordering.
     *
     * @param  array<string, mixed>  $args
     */
    public function stockMovements(Ingredient $ingredient, array $args)
    {
        $query = $ingredient->stockMovements();

        $orders = $args['orderBy'] ?? [[
            'column' => 'created_at',
            'order' => 'DESC',
        ]];

        foreach ($orders as $order) {
            $query->orderBy($order['column'], $order['order']);
        }

        return $query->get();
    }
}
