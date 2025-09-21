<?php

namespace Database\Seeders;

use App\Enums\MeasurementUnit;
use App\Enums\MenuServiceType;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuType;
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
            $locations = Location::where('company_id', $company->id)->get();
            $categories = MenuCategory::where('company_id', $company->id)->get();
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

            $priorityPerType = array_fill_keys($menuTypeIds, 0);

            if ($company->name === 'Charlie Kirk') {
                $this->createBoBunHayMeanMenu($company, $ingredients);
            }

            if ($company->name === 'Charlie Kirk') {
                $this->createTastyCroustyMenu($company, $ingredients);
            }

            for ($i = 0; $i < 5; $i++) {
                $menuTypeId = Arr::random($menuTypeIds);
                $priority = $priorityPerType[$menuTypeId] ?? 0;
                $priorityPerType[$menuTypeId] = $priority + 1;

                $menu = Menu::factory()->create([
                    'company_id' => $company->id,
                    'service_type' => fake()->randomElement(MenuServiceType::values()),
                    'is_returnable' => fake()->boolean(),
                    'menu_type_id' => $menuTypeId,
                    'public_priority' => $priority,
                    'price' => fake()->randomFloat(2, 5, 50),
                ]);

                if ($categories->count() > 0) {
                    $menu->categories()->sync(
                        $categories->random(rand(0, min(3, $categories->count())))->pluck('id')->toArray()
                    );
                }

                if ($ingredients->count() === 0 || $locations->count() === 0) {
                    continue;
                }

                $selected = $ingredients->random(min(2, $ingredients->count()));
                foreach ($selected as $ingredient) {
                    $location = $ingredient->locations()->inRandomOrder()->first();
                    MenuItem::create([
                        'menu_id' => $menu->id,
                        'entity_id' => $ingredient->id,
                        'entity_type' => Ingredient::class,
                        'quantity' => 1,
                        'unit' => $ingredient->unit instanceof MeasurementUnit ? $ingredient->unit->value : $ingredient->unit,
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
        $menu->type = 'plat';
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
            $menu->type = 'plat';
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
}
