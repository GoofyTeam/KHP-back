<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MenuCardMenuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $hasStock = $this->resource->getAttribute('has_sufficient_stock');

        if ($hasStock === null) {
            $hasStock = $this->resource->hasSufficientStock();
        }

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'type' => $this->resource->type,
            'price' => $this->resource->price,
            'image_url' => $this->resource->image_url,
            'has_sufficient_stock' => $hasStock,
            'categories' => $this->resource->categories
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])
                ->values()
                ->all(),
            'allergens' => $this->resource->allergens,
        ];
    }
}
