<?php

namespace App\GraphQL\Resolvers;

use App\Models\Ingredient;
use App\DTO\OpenFoodFactsDTO;
use App\Services\OpenFoodFactsService;

class OpenFoodFactsResolver
{
    protected OpenFoodFactsService $service;

    public function __construct(OpenFoodFactsService $service)
    {
        $this->service = $service;
    }

    /**
     * Résolveur pour search en fonction du code-barres ou des mots-clés pour openfoodfacts.
     *
     * @param  mixed  $_
     */
    public function search($_, array $args): ?OpenFoodFactsDTO
    {
        if (! empty($args['barcode'])) {

            $user = auth()->user();

            $ingredient = Ingredient::where('company_id', $user->company_id)
                ->where('barcode', $args['barcode'])
                ->first();

            if ($ingredient) {

                return new OpenFoodFactsDTO([
                    'code' => $ingredient->barcode,
                    'product_name_fr' => $ingredient->name,
                    'product_quantity' => $ingredient->base_quantity,
                    'product_quantity_unit' => $ingredient->unit->value,
                    /**
                     * @phpstan-ignore-next-line
                     */
                    'categories' => $ingredient->category->name,
                    'image_front_url' => $ingredient->image_url ? url('/api/image-proxy/' . $ingredient->image_url) : null,
                    'is_already_in_database' => true,
                    'ingredient_id' => $ingredient->id,
                ]);
            }

            return new OpenFoodFactsDTO($this->service->searchByBarcode($args['barcode']));
        }

        $keyword = $args['keyword'] ?? null;
        $page = $args['page'] ?? 1;
        $pageSize = $args['pageSize'] ?? 20;

        if ($keyword) {
            return new OpenFoodFactsDTO($this->service->searchByKeyword($keyword, $page, $pageSize));
        }

        return null;
    }
}
