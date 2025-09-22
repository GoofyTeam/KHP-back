<?php

namespace Database\Seeders;

use App\Enums\MeasurementUnit;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuType;
use App\Models\Preparation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::all();

        foreach ($companies as $company) {
            $ingredients = Ingredient::where('company_id', $company->id)
                ->whereHas('locations')
                ->get();
            $preparations = Preparation::where('company_id', $company->id)
                ->whereHas('locations')
                ->get();
            $menuTypeIds = MenuType::where('company_id', $company->id)->pluck('id')->all();

            if (empty($menuTypeIds)) {
                $defaultTypes = [
                    ['name' => 'Entrées', 'position' => 0],
                    ['name' => 'Plats', 'position' => 1],
                    ['name' => 'Desserts', 'position' => 2],
                    ['name' => 'Accompagnements', 'position' => 3],
                ];

                foreach ($defaultTypes as $type) {
                    $menuType = MenuType::firstOrCreate(
                        [
                            'company_id' => $company->id,
                            'name' => $type['name'],
                        ]
                    );

                    $menuType->publicOrder()->updateOrCreate(
                        ['company_id' => $company->id],
                        ['position' => $type['position']]
                    );

                    $menuTypeIds[] = $menuType->id;
                }
            }

            if (empty($menuTypeIds)) {
                continue;
            }

            if ($company->name === 'Charlie Kirk') {
                $this->createBoBunHayMeanMenu($company, $ingredients);
            }

            if ($company->name === 'Charlie Kirk') {
                $this->createTastyCroustyMenu($company, $ingredients);
            }

            if ($company->name === 'GoofyTeam') {
                $this->createGoofyTeamMenus($company, $ingredients, $preparations);
            }
        }
    }

    private function createGoofyTeamMenus(Company $company, Collection $ingredients, Collection $preparations): void
    {
        if ($ingredients->isEmpty() && $preparations->isEmpty()) {
            return;
        }

        $menus = [
            [
                'name' => 'Menu Goofy Terroir',
                'description' => 'Poulet rôti au citron, purée maison au beurre doux et salade fraîche.',
                'price' => 17.5,
                'items' => [
                    ['name' => 'Poulet citron ail', 'entity_type' => Preparation::class, 'quantity' => 0.35],
                    ['name' => 'Purée de pommes de terre', 'entity_type' => Preparation::class, 'quantity' => 0.3],
                    ['name' => 'Salades', 'entity_type' => Ingredient::class, 'quantity' => 0.12],
                ],
            ],
            [
                'name' => 'Menu Goofy Marin',
                'description' => 'Saumon grillé, salade de tomates au basilic et touche de citron frais.',
                'price' => 19.2,
                'items' => [
                    ['name' => 'Saumon grillé au citron', 'entity_type' => Preparation::class, 'quantity' => 0.32],
                    ['name' => 'Salade de tomates au basilic', 'entity_type' => Preparation::class, 'quantity' => 1],
                    ['name' => 'Citron', 'entity_type' => Ingredient::class, 'quantity' => 0.05],
                ],
            ],
            [
                'name' => 'Menu Goofy Veggie',
                'description' => 'Ratatouille express, lentilles au curry et basilic frais.',
                'price' => 15.9,
                'items' => [
                    ['name' => 'Ratatouille express', 'entity_type' => Preparation::class, 'quantity' => 0.35],
                    ['name' => 'Lentilles au curry', 'entity_type' => Preparation::class, 'quantity' => 0.3],
                    ['name' => 'Basilic frais', 'entity_type' => Ingredient::class, 'quantity' => 0.01],
                ],
            ],
            [
                'name' => 'Menu Goofy Brunch',
                'description' => 'Œufs brouillés, bananes caramélisées et fruits frais.',
                'price' => 14.4,
                'items' => [
                    ['name' => 'Œufs brouillés', 'entity_type' => Preparation::class, 'quantity' => 1],
                    ['name' => 'Bananes caramélisées', 'entity_type' => Preparation::class, 'quantity' => 1],
                    ['name' => 'Pommes', 'entity_type' => Ingredient::class, 'quantity' => 0.2],
                ],
            ],
            [
                'name' => 'Menu Goofy Pasta',
                'description' => 'Spaghetti tomate-basilic, salade fraîche et fromage râpé.',
                'price' => 16.3,
                'items' => [
                    ['name' => 'Spaghetti tomate-basilic', 'entity_type' => Preparation::class, 'quantity' => 0.4],
                    ['name' => 'Salade de tomates au basilic', 'entity_type' => Preparation::class, 'quantity' => 1],
                    ['name' => 'Fromage râpé (emmental, parmesan)', 'entity_type' => Ingredient::class, 'quantity' => 0.05],
                ],
            ],
        ];

        foreach ($menus as $data) {
            $menu = Menu::firstOrNew([
                'company_id' => $company->id,
                'name' => $data['name'],
            ]);

            $menu->description = $data['description'];
            $menu->is_a_la_carte = true;
            $menu->menu_type_id = $this->resolveMenuTypeId($company, 'Plats');
            $menu->price = $data['price'];

            if ($image = $this->resolveMenuImageFromSlug(Str::slug($data['name']))) {
                $menu->image_url = $image;
            }

            $menu->save();
            $menu->items()->delete();

            foreach ($data['items'] as $item) {
                $entityType = $item['entity_type'];

                if ($entityType === Ingredient::class) {
                    $entity = $ingredients->firstWhere('name', $item['name'])
                        ?? Ingredient::where('company_id', $company->id)
                            ->where('name', $item['name'])
                            ->first();

                    if (! $entity) {
                        continue;
                    }

                    $location = $entity->locations()
                        ->wherePivot('quantity', '>', 0)
                        ->inRandomOrder()
                        ->first()
                        ?? $entity->locations()->inRandomOrder()->first();

                    if (! $location) {
                        continue;
                    }

                    $unit = $entity->unit instanceof MeasurementUnit ? $entity->unit->value : $entity->unit;

                    MenuItem::create([
                        'menu_id' => $menu->id,
                        'entity_id' => $entity->id,
                        'entity_type' => Ingredient::class,
                        'quantity' => $item['quantity'],
                        'unit' => $unit,
                        'location_id' => $location->id,
                    ]);

                    continue;
                }

                if ($entityType === Preparation::class) {
                    $preparation = $preparations->firstWhere('name', $item['name'])
                        ?? Preparation::where('company_id', $company->id)
                            ->where('name', $item['name'])
                            ->with('locations')
                            ->first();

                    if (! $preparation) {
                        continue;
                    }

                    $preparation->loadMissing('locations');

                    $location = $preparation->locations
                        ->filter(fn ($loc) => ($loc->pivot->quantity ?? 0) > 0)
                        ->sortByDesc(fn ($loc) => $loc->pivot->quantity ?? 0)
                        ->first()
                        ?? $preparation->locations->first();

                    if (! $location) {
                        continue;
                    }

                    $unit = $preparation->unit instanceof MeasurementUnit
                        ? $preparation->unit->value
                        : $preparation->unit;

                    MenuItem::create([
                        'menu_id' => $menu->id,
                        'entity_id' => $preparation->id,
                        'entity_type' => Preparation::class,
                        'quantity' => $item['quantity'],
                        'unit' => $unit,
                        'location_id' => $location->id,
                    ]);
                }
            }
        }
    }

    private function createBoBunHayMeanMenu(Company $company, Collection $ingredients): void
    {
        $menu = Menu::firstOrNew([
            'company_id' => $company->id,
            'name' => 'BòBún Hay Mean',
        ]);

        $menu->description = 'Également connu sous le surnom « Chinois marrant »';
        $menu->is_a_la_carte = true;
        $menu->menu_type_id = $this->resolveMenuTypeId($company, 'Plats');
        $menu->price = 13.9;

        if ($image = $this->resolveMenuImageFromSlug('bobun-hay-mean')) {
            $menu->image_url = $image;
        }

        $menu->save();
        $menu->items()->delete();

        if ($ingredients->isEmpty()) {
            return;
        }

        $components = [
            ['name' => 'Entrecôte de bœuf', 'quantity' => 0.15],
            ['name' => 'Riz basmati et riz arborio', 'quantity' => 0.12],
            ['name' => 'Carottes', 'quantity' => 0.08],
            ['name' => 'Salades (mélange, batavia, roquette)', 'quantity' => 0.05],
            ['name' => 'Basilic frais', 'quantity' => 0.01],
        ];

        foreach ($components as $component) {
            $ingredient = $ingredients->firstWhere('name', $component['name'])
                ?? Ingredient::where('company_id', $company->id)
                    ->where('name', $component['name'])
                    ->first();

            if (! $ingredient) {
                continue;
            }

            $location = $ingredient->locations()->inRandomOrder()->first();

            if (! $location) {
                continue;
            }

            MenuItem::create([
                'menu_id' => $menu->id,
                'entity_id' => $ingredient->id,
                'entity_type' => Ingredient::class,
                'quantity' => $component['quantity'],
                'unit' => $ingredient->unit instanceof MeasurementUnit ? $ingredient->unit->value : $ingredient->unit,
                'location_id' => $location->id,
            ]);
        }
    }

    private function createTastyCroustyMenu(Company $company, Collection $ingredients): void
    {
        $menus = [
            [
                'name' => 'Crousty Chicken',
                'description' => "Base de riz, tenders de poulet,\nsauce crousty maison, sauce barbecue,\nsauce spicy légère, oignon frit,\nciboulette, persil",
                'components' => [
                    ['name' => 'Coca Cherry', 'quantity' => 1.0],
                    ['name' => 'Crousty', 'quantity' => 1.0],
                ],
            ],
            [
                'name' => 'Crousty Boursin',
                'description' => "Base de riz boursin, tenders de poulet,\nsauce crousty maison, sauce aigre-douce,\nsauce spicy légère, oignon frit,\nciboulette, persil",
                'components' => [
                    ['name' => 'Coca Cherry', 'quantity' => 1.0],
                    ['name' => 'Crousty', 'quantity' => 1.0],
                ],
            ],
            [
                'name' => 'Crousty Curry',
                'description' => "Base de riz curry, tenders de poulet,\nsauce crousty maison, sauce curry,\nsauce spicy légère, oignon frit,\nciboulette, persil",
                'components' => [
                    ['name' => 'Coca Cherry', 'quantity' => 1.0],
                    ['name' => 'Crousty', 'quantity' => 1.0],
                ],
            ],
            [
                'name' => 'Crousty Cordon',
                'description' => "Base de riz, cordon bleu, sauce crousty\nmaison, sauce biggy, oignon frit,\nciboulette, persil",
                'components' => [
                    ['name' => 'Coca Cherry', 'quantity' => 1.0],
                    ['name' => 'Crousty', 'quantity' => 1.0],
                ],
            ],
            [
                'name' => 'Burger Brioché',
                'description' => "2 gros tenders, cheddar, oignons frits,\nsauce biggy",
                'components' => [
                    ['name' => 'Coca Cherry', 'quantity' => 1.0],
                    ['name' => 'Crousty', 'quantity' => 1.0],
                ],
            ],
        ];

        foreach ($menus as $data) {
            $menu = Menu::firstOrNew([
                'company_id' => $company->id,
                'name' => $data['name'],
            ]);

            $menu->description = $data['description'];
            $menu->is_a_la_carte = true;
            $menu->menu_type_id = $this->resolveMenuTypeId($company, 'Plats');
            $menu->price = 12.0;

            if ($image = $this->resolveMenuImageFromSlug(Str::slug($data['name']))) {
                $menu->image_url = $image;
            }

            $menu->save();
            $menu->items()->delete();

            if ($ingredients->isEmpty()) {
                continue;
            }

            foreach ($data['components'] as $component) {
                $ingredient = $ingredients->firstWhere('name', $component['name'])
                    ?? Ingredient::where('company_id', $company->id)
                        ->where('name', $component['name'])
                        ->first();

                if (! $ingredient) {
                    continue;
                }

                $location = $ingredient->locations()->inRandomOrder()->first();

                if (! $location) {
                    continue;
                }

                MenuItem::create([
                    'menu_id' => $menu->id,
                    'entity_id' => $ingredient->id,
                    'entity_type' => Ingredient::class,
                    'quantity' => $component['quantity'],
                    'unit' => $ingredient->unit instanceof MeasurementUnit ? $ingredient->unit->value : $ingredient->unit,
                    'location_id' => $location->id,
                ]);
            }
        }
    }

    private function resolveMenuTypeId(Company $company, string $name): ?int
    {
        $menuType = MenuType::firstOrCreate([
            'company_id' => $company->id,
            'name' => $name,
        ]);

        $position = optional($menuType->publicOrder)->position;

        if ($position === null) {
            $position = MenuType::where('company_id', $company->id)->count() - 1;
        }

        $menuType->publicOrder()->updateOrCreate(
            ['company_id' => $company->id],
            ['position' => max($position, 0)]
        );

        return $menuType->id;
    }

    private function resolveMenuImageFromSlug(string $slug): ?string
    {
        $localDisk = Storage::disk('local');
        $directories = ['images/seeders/images', 'seeders/images'];
        $extensions = ['jpeg', 'jpg', 'png', 'webp', 'gif', 'avif'];

        foreach ($directories as $directory) {
            $normalizedDirectory = ltrim(preg_replace('#^private/#', '', $directory), '/');
            if ($normalizedDirectory === '') {
                continue;
            }

            foreach ($extensions as $extension) {
                $relativePath = trim($normalizedDirectory, '/')."/{$slug}.{$extension}";

                if (! $localDisk->exists($relativePath)) {
                    continue;
                }

                $normalizedPath = 'storage/app/private/'.ltrim($relativePath, '/');

                try {
                    $contents = $localDisk->get($relativePath);

                    try {
                        $s3 = Storage::disk('s3');
                        if (! $s3->exists($normalizedPath)) {
                            $s3->put($normalizedPath, $contents);
                        }
                    } catch (\Throwable $e) {
                        // Ignore S3 errors and keep local path fallback
                    }
                } catch (\Throwable $e) {
                    // Cannot read file contents, still return local path fallback
                }

                return $normalizedPath;
            }
        }

        return null;
    }

    private function resolveMenuTypeId(Company $company, string $typeName): ?int
    {
        $menuType = MenuType::firstOrCreate(
            [
                'company_id' => $company->id,
                'name' => $typeName,
            ]
        );

        return $menuType->id;
    }
}
