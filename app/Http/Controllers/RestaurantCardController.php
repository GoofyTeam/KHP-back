<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Company;
use App\Models\Menu;
use App\Support\PublicCardUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RestaurantCardController extends Controller
{
    public function __invoke(string $public_card_url): JsonResponse
    {
        $slug = PublicCardUrl::format($public_card_url);

        if (strlen($slug) > 64) {
            throw ValidationException::withMessages([
                'public_card_url' => 'Le format de l\'URL publique est invalide.',
            ]);
        }

        /** @var Company $company */
        $company = Company::where('public_card_url', $slug)->firstOrFail();

        /**
         * @var \Illuminate\Support\Collection<int, array{menu: Menu, has_stock: bool}> $menusForCard
         */
        $menusForCard = Menu::query()
            ->where('company_id', $company->id)
            ->where('is_a_la_carte', true)
            ->with(['items.entity', 'categories'])
            ->get()
            ->map(fn (Menu $menu): array => [
                'menu' => $menu,
                'has_stock' => $menu->hasSufficientStock(),
            ]);

        /**
         * @var \Illuminate\Support\Collection<int, array<string, mixed>> $menus
         */
        $menus = $menusForCard
            ->when(
                ! $company->show_out_of_stock_menus,
                fn ($collection) => $collection->filter(fn (array $data): bool => $data['has_stock'])
            )
            ->sortByDesc(fn (array $data): int => (int) $data['has_stock'])
            ->values()
            ->map(function (array $data) use ($company) {
                /** @var Menu $menu */
                $menu = $data['menu'];
                $hasStock = $data['has_stock'];

                return [
                    'id' => $menu->id,
                    'name' => $menu->name,
                    'description' => $menu->description,
                    'type' => $menu->type,
                    'price' => $menu->price,
                    'has_sufficient_stock' => $hasStock,
                    'image_url' => $company->show_menu_images ? $menu->image_url : null,
                    'categories' => $menu->categories
                        ->map(function ($category): array {
                            /** @var Category $category */
                            return [
                                'id' => $category->id,
                                'name' => $category->name,
                            ];
                        })->values(),
                    'allergens' => $menu->allergens,
                ];
            });

        return response()->json([
            'company' => [
                'name' => $company->name,
                'public_card_url' => $company->public_card_url,
                'menus' => $menus,
            ],
        ]);
    }
}
