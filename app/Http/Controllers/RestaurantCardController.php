<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowRestaurantCardRequest;
use App\Http\Resources\MenuCardMenuResource;
use App\Models\Company;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;

class RestaurantCardController extends Controller
{
    public function show(ShowRestaurantCardRequest $request): JsonResponse
    {
        $slug = $request->validated()['public_menu_card_url'];

        /** @var Company $company */
        $company = Company::where('public_menu_card_url', $slug)->firstOrFail();

        $menus = Menu::query()
            ->where('company_id', $company->id)
            ->where('is_a_la_carte', true)
            ->with(['categories:id,name'])
            ->get()
            ->map(function (Menu $menu) use ($company) {
                $menu->setAttribute('has_sufficient_stock', $menu->hasSufficientStock());

                if (! $company->show_menu_images) {
                    $menu->image_url = null;
                }

                return $menu;
            });

        if (! $company->show_out_of_stock_menus_on_card) {
            $menus = $menus->filter(fn (Menu $menu) => $menu->getAttribute('has_sufficient_stock'));
        }

        $menus = $menus
            ->sortByDesc(fn (Menu $menu) => (int) $menu->getAttribute('has_sufficient_stock'))
            ->values();

        return response()->json([
            'company' => [
                'name' => $company->name,
                'public_menu_card_url' => $company->public_menu_card_url,
                'menus' => MenuCardMenuResource::collection($menus),
            ],
        ]);
    }
}
