<?php

namespace App\Http\Resources;

use App\Models\MenuTypePublicOrder;
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

        $imageUrl = null;
        $imagePath = $this->resource->image_url;
        $menuType = $this->resource->menuType;
        $publicOrder = $menuType?->publicOrder;

        $publicOrder = $publicOrder instanceof MenuTypePublicOrder ? $publicOrder : null;

        if (is_string($imagePath) && filter_var($imagePath, FILTER_VALIDATE_URL)) {
            $imageUrl = $imagePath;
        } elseif ($imagePath && $slug = $this->resource->getAttribute('public_menu_card_url')) {
            $segments = explode('/', $imagePath, 2);

            if (count($segments) === 2 && $segments[0] !== '' && $segments[1] !== '') {
                [$bucket, $path] = $segments;
                $imageUrl = url(sprintf(
                    '/api/public/image-proxy/%s/%s/%s',
                    $slug,
                    $bucket,
                    $path
                ));
            }
        }

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'type' => $this->resource->type,
            'type_index' => $publicOrder ? $publicOrder->position : 0,
            'menu_type_id' => $this->resource->menu_type_id,
            'priority' => (int) ($this->resource->public_priority ?? 0),
            'price' => $this->resource->price,
            'image_url' => $imageUrl,
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
