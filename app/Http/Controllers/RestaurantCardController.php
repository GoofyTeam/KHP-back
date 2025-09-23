<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowRestaurantCardRequest;
use App\Http\Resources\MenuCardMenuResource;
use App\Models\Company;
use App\Models\Menu;
use App\Models\MenuType;
use App\Models\MenuTypePublicOrder;
use Illuminate\Http\JsonResponse;

class RestaurantCardController extends Controller
{
    public function show(ShowRestaurantCardRequest $request): JsonResponse
    {
        $slug = $request->validated()['public_menu_card_url'];

        /** @var Company $company */
        $company = Company::where('public_menu_card_url', $slug)
            ->with(['businessHours' => fn ($query) => $query
                ->orderBy('day_of_week')
                ->orderBy('sequence')])
            ->firstOrFail();

        $menus = Menu::query()
            ->select('menus.*')
            ->where('menus.company_id', $company->id)
            ->where('menus.is_a_la_carte', true)
            ->leftJoin('menu_type_public_orders as mtpo', function ($join) use ($company) {
                $join->on('mtpo.menu_type_id', '=', 'menus.menu_type_id')
                    ->where('mtpo.company_id', '=', $company->id);
            })
            ->with(['categories:id,name', 'menuType.publicOrder'])
            ->orderByRaw('COALESCE(mtpo.position, 2147483647)')
            ->orderBy('menus.public_priority')
            ->orderBy('menus.name')
            ->orderBy('menus.id')
            ->get()
            ->map(function (Menu $menu) use ($company) {
                $menu->setAttribute('has_sufficient_stock', $menu->hasSufficientStock());
                $menu->setAttribute('public_menu_card_url', $company->public_menu_card_url);

                if (! $company->show_menu_images) {
                    $menu->image_url = null;
                }

                return $menu;
            });

        if (! $company->show_out_of_stock_menus_on_card) {
            $menus = $menus->filter(fn (Menu $menu) => $menu->getAttribute('has_sufficient_stock'));
        }

        $menus = $menus
            ->sort(function (Menu $first, Menu $second) {
                /** @var MenuType|null $firstMenuType */
                $firstMenuType = $first->menuType;
                /** @var MenuType|null $secondMenuType */
                $secondMenuType = $second->menuType;

                $firstPublicOrder = $firstMenuType?->publicOrder;
                $secondPublicOrder = $secondMenuType?->publicOrder;

                $firstTypeIndex = $firstPublicOrder instanceof MenuTypePublicOrder
                    ? $firstPublicOrder->position
                    : PHP_INT_MAX;

                $secondTypeIndex = $secondPublicOrder instanceof MenuTypePublicOrder
                    ? $secondPublicOrder->position
                    : PHP_INT_MAX;

                $firstKey = [
                    $firstTypeIndex,
                    $first->getAttribute('has_sufficient_stock') ? 0 : 1,
                    (int) ($first->public_priority ?? 0),
                    mb_strtolower($first->name ?? ''),
                    (int) $first->id,
                ];

                $secondKey = [
                    $secondTypeIndex,
                    $second->getAttribute('has_sufficient_stock') ? 0 : 1,
                    (int) ($second->public_priority ?? 0),
                    mb_strtolower($second->name ?? ''),
                    (int) $second->id,
                ];

                return $firstKey <=> $secondKey;
            })
            ->values();

        $logoPath = $company->logo_path;
        $logoUrl = $this->resolveImageUrl($logoPath, $company->public_menu_card_url);

        return response()->json([
            'company' => [
                'name' => $company->name,
                'public_menu_card_url' => $company->public_menu_card_url,
                'logo_url' => $logoUrl,
                'contact' => [
                    'name' => $company->contact_name,
                    'email' => $company->contact_email,
                    'phone' => $company->contact_phone,
                ],
                'address' => [
                    'line' => $company->address_line,
                    'postal_code' => $company->postal_code,
                    'city' => $company->city,
                    'country' => $company->country,
                ],
                'business_hours' => $company->businessHours
                    ->map(fn ($hour) => [
                        'day_of_week' => $hour->day_of_week,
                        'opens_at' => $hour->opens_at,
                        'closes_at' => $hour->closes_at,
                        'is_overnight' => (bool) $hour->is_overnight,
                        'sequence' => $hour->sequence,
                    ])
                    ->values()
                    ->all(),
                'settings' => [
                    'show_out_of_stock_menus_on_card' => (bool) $company->show_out_of_stock_menus_on_card,
                    'show_menu_images' => (bool) $company->show_menu_images,
                ],
                'menus' => MenuCardMenuResource::collection($menus),
            ],
        ]);
    }

    private function resolveImageUrl(?string $path, string $slug): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $segments = explode('/', $path, 2);
        if (count($segments) !== 2) {
            return null;
        }

        [$bucket, $subPath] = $segments;

        if ($bucket === '' || $subPath === '') {
            return null;
        }

        return url(sprintf('/api/public/image-proxy/%s/%s/%s', $slug, $bucket, $subPath));
    }
}
