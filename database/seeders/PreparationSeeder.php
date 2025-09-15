<?php

namespace Database\Seeders;

use App\Enums\MeasurementUnit;
use App\Models\Category;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Preparation;
use App\Models\PreparationEntity;
use App\Services\ImageService;
use App\Services\UnitConversionService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PreparationSeeder extends Seeder
{
    private ImageService $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::where('name', 'GoofyTeam')->first();

        // Précharge les images locales disponibles pour le matching par nom
        $localImages = $this->listLocalImageFiles();

        // Ajoute uniquement 10 préparations nommées basées sur la liste d'IngredientSeeder
        $this->seedGoofyTeamSpecificPreparations($company, $localImages, new UnitConversionService);
    }

    /**
     * Crée 10 préparations nommées en utilisant exclusivement des ingrédients
     * présents dans la liste de GoofyTeam (IngredientSeeder) et des catégories existantes.
     */
    private function seedGoofyTeamSpecificPreparations(
        Company $company,
        array $localImages,
        UnitConversionService $converter
    ): void {
        $categories = Category::where('company_id', $company->id)->pluck('id', 'name')->all();
        // Liste des emplacements pour pouvoir y attribuer des stocks initiaux
        $locationIds = $company->locations()->pluck('id')->all();

        $recipes = [
            [
                'name' => 'Salade de tomates au basilic',
                'category' => 'Salades',
                'unit' => MeasurementUnit::UNIT,
                'base_quantity' => 1,
                'base_unit' => MeasurementUnit::UNIT,
                'components' => [
                    ['name' => 'Tomates fraîches', 'quantity' => 250, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Basilic frais', 'quantity' => 15, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Huile d’olive', 'quantity' => 30, 'unit' => MeasurementUnit::MILLILITRE],
                    ['name' => 'Sel fin', 'quantity' => 2, 'unit' => MeasurementUnit::GRAM],
                ],
            ],
            [
                'name' => 'Soupe poireaux-pommes de terre',
                'category' => 'Soupes',
                'unit' => MeasurementUnit::LITRE,
                'base_quantity' => 2,
                'base_unit' => MeasurementUnit::LITRE,
                'components' => [
                    ['name' => 'Poireaux', 'quantity' => 400, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Pommes de terre', 'quantity' => 600, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Crème fraîche', 'quantity' => 150, 'unit' => MeasurementUnit::MILLILITRE],
                    ['name' => 'Sel fin', 'quantity' => 5, 'unit' => MeasurementUnit::GRAM],
                ],
            ],
            [
                'name' => 'Purée de pommes de terre',
                'category' => 'Plats Préparés',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 1.2,
                'base_unit' => MeasurementUnit::KILOGRAM,
                'components' => [
                    ['name' => 'Pommes de terre', 'quantity' => 800, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Beurre doux', 'quantity' => 100, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Lait entier', 'quantity' => 200, 'unit' => MeasurementUnit::MILLILITRE],
                    ['name' => 'Sel fin', 'quantity' => 3, 'unit' => MeasurementUnit::GRAM],
                ],
            ],
            [
                'name' => 'Ratatouille express',
                'category' => 'Plats Préparés',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 1.2,
                'base_unit' => MeasurementUnit::KILOGRAM,
                'components' => [
                    ['name' => 'Courgettes', 'quantity' => 400, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Tomates fraîches', 'quantity' => 400, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Oignons jaunes', 'quantity' => 200, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Ail', 'quantity' => 10, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Huile d’olive', 'quantity' => 30, 'unit' => MeasurementUnit::MILLILITRE],
                ],
            ],
            [
                'name' => 'Poulet citron ail',
                'category' => 'Plats Préparés',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 1,
                'base_unit' => MeasurementUnit::KILOGRAM,
                'components' => [
                    ['name' => 'Poitrine de poulet', 'quantity' => 700, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Citron', 'quantity' => 150, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Ail', 'quantity' => 8, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Huile d’olive', 'quantity' => 20, 'unit' => MeasurementUnit::MILLILITRE],
                    ['name' => 'Poivre noir en grains', 'quantity' => 5, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Sel fin', 'quantity' => 5, 'unit' => MeasurementUnit::GRAM],
                ],
            ],
            [
                'name' => 'Saumon grillé au citron',
                'category' => 'Plats Préparés',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 1,
                'base_unit' => MeasurementUnit::KILOGRAM,
                'components' => [
                    ['name' => 'Saumon frais (filet)', 'quantity' => 800, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Citron', 'quantity' => 100, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Huile d’olive', 'quantity' => 15, 'unit' => MeasurementUnit::MILLILITRE],
                    ['name' => 'Sel fin', 'quantity' => 4, 'unit' => MeasurementUnit::GRAM],
                ],
            ],
            [
                'name' => 'Spaghetti tomate-basilic',
                'category' => 'Plats Préparés',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 1.2,
                'base_unit' => MeasurementUnit::KILOGRAM,
                'components' => [
                    ['name' => 'Pâtes (fusilli, penne, spaghetti)', 'quantity' => 500, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Tomates pelées en conserve', 'quantity' => 400, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Basilic frais', 'quantity' => 10, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Ail', 'quantity' => 6, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Huile d’olive', 'quantity' => 25, 'unit' => MeasurementUnit::MILLILITRE],
                    ['name' => 'Sel fin', 'quantity' => 5, 'unit' => MeasurementUnit::GRAM],
                ],
            ],
            [
                'name' => 'Œufs brouillés',
                'category' => 'Plats Préparés',
                'unit' => MeasurementUnit::UNIT,
                'base_quantity' => 4,
                'base_unit' => MeasurementUnit::UNIT,
                'components' => [
                    ['name' => 'Œufs frais', 'quantity' => 4, 'unit' => MeasurementUnit::UNIT],
                    ['name' => 'Beurre doux', 'quantity' => 30, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Sel fin', 'quantity' => 2, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Poivre noir en grains', 'quantity' => 1, 'unit' => MeasurementUnit::GRAM],
                ],
            ],
            [
                'name' => 'Lentilles au curry',
                'category' => 'Plats Préparés',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 1.1,
                'base_unit' => MeasurementUnit::KILOGRAM,
                'components' => [
                    ['name' => 'Lentilles vertes', 'quantity' => 400, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Oignons jaunes', 'quantity' => 150, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Huile de tournesol', 'quantity' => 15, 'unit' => MeasurementUnit::MILLILITRE],
                    ['name' => 'Paprika fumé', 'quantity' => 5, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Ail', 'quantity' => 8, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Sel fin', 'quantity' => 5, 'unit' => MeasurementUnit::GRAM],
                ],
            ],
            [
                'name' => 'Bananes caramélisées',
                'category' => 'Desserts et Pâtisseries',
                'unit' => MeasurementUnit::UNIT,
                'base_quantity' => 4,
                'base_unit' => MeasurementUnit::UNIT,
                'components' => [
                    ['name' => 'Bananes', 'quantity' => 400, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Sucre semoule', 'quantity' => 40, 'unit' => MeasurementUnit::GRAM],
                    ['name' => 'Beurre doux', 'quantity' => 30, 'unit' => MeasurementUnit::GRAM],
                ],
            ],
        ];

        foreach ($recipes as $recipe) {
            $categoryId = $categories[$recipe['category']] ?? (reset($categories) ?: null);
            if (! $categoryId) {
                continue;
            }

            // Ajoute une image locale correspondant au nom si elle est présente
            $imageUrl = null;
            $matched = $this->findLocalImagePath($recipe['name'], $localImages);
            if ($matched) {
                try {
                    $absolute = method_exists(Storage::disk('local'), 'path')
                        ? Storage::disk('local')->path($matched)
                        : storage_path('app/'.$matched);
                    $mime = @mime_content_type($absolute) ?: 'image/jpeg';
                    $upload = new UploadedFile($absolute, basename($absolute), $mime, null, true);
                    $imageUrl = $this->imageService->store($upload, 'preparations');
                } catch (\Throwable $e) {
                    $imageUrl = null;
                }
            }

            $prep = Preparation::factory()->create([
                'company_id' => $company->id,
                'name' => $recipe['name'],
                'category_id' => $categoryId,
                'unit' => $recipe['unit']->value,
                'base_quantity' => $recipe['base_quantity'] ?? 1,
                'base_unit' => ($recipe['base_unit'] ?? $recipe['unit'])->value,
                'image_url' => $imageUrl,
            ]);

            // Lier les ingrédients avec quantités, unités et emplacements
            foreach ($recipe['components'] as $component) {
                $ingredient = Ingredient::where('company_id', $company->id)
                    ->where('name', $component['name'])
                    ->with('locations')
                    ->first();

                if (! $ingredient) {
                    continue;
                }

                $location = $ingredient->locations
                    ->sortByDesc(fn ($loc) => $loc->pivot->quantity)
                    ->first(fn ($loc) => ($loc->pivot->quantity ?? 0) > 0)
                    ?? $ingredient->locations->first();

                if (! $location && ! empty($locationIds)) {
                    $targetLocationId = collect($locationIds)->random();
                    $requiredInIngredientUnit = $component['quantity'];
                    if ($component['unit'] !== $ingredient->unit) {
                        $requiredInIngredientUnit = $converter->convert(
                            $component['quantity'],
                            $component['unit'],
                            $ingredient->unit
                        );
                    }

                    $seedQuantity = max(round($requiredInIngredientUnit * 5, 2), 1);
                    $ingredient->locations()->syncWithoutDetaching([
                        $targetLocationId => ['quantity' => $seedQuantity],
                    ]);
                    $ingredient->load('locations');
                    $location = $ingredient->locations->firstWhere('id', $targetLocationId);
                }

                if (! $location) {
                    continue;
                }

                PreparationEntity::updateOrCreate(
                    [
                        'preparation_id' => $prep->id,
                        'entity_id' => $ingredient->id,
                        'entity_type' => Ingredient::class,
                        'location_id' => $location->id,
                    ],
                    [
                        'quantity' => $component['quantity'],
                        'unit' => $component['unit']->value,
                    ]
                );
            }

            // Attache la préparation à quelques emplacements avec quantités
            if (! empty($locationIds)) {
                // Sélectionne aléatoirement jusqu'à 3 emplacements de stockage
                $take = rand(1, min(3, count($locationIds)));
                $selected = collect($locationIds)->shuffle()->take($take);
                foreach ($selected as $locId) {
                    // ~15 % des stocks démarrent à zéro pour simuler une rupture
                    if (rand(1, 100) <= 15) {
                        $qty = 0;
                    } else {
                        $qty = $prep->unit === MeasurementUnit::UNIT
                            ? round(rand(2, 6) / 2, 2)
                            : round(rand(10, 150) / 10, 2);
                    }
                    $prep->locations()->attach($locId, ['quantity' => $qty]);
                }
            }
        }
    }

    // Helpers d’images locales (similaires à IngredientSeeder)
    protected function listLocalImageFiles(): array
    {
        $disk = Storage::disk('local');
        $folders = ['private/seeders/images', 'seeders/images'];

        return collect($folders)
            ->flatMap(fn ($dir) => $disk->exists($dir) ? $disk->files($dir) : [])
            ->filter(function (string $path) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
            })
            ->values()
            ->all();
    }

    protected function findLocalImagePath(string $name, array $files): ?string
    {
        if (empty($files)) {
            return null;
        }

        $candidates = $this->nameCandidates($name);

        $indexed = array_map(function ($rel) {
            $base = pathinfo($rel, PATHINFO_FILENAME);

            return [
                'rel' => $rel,
                'base' => $base,
                'norm' => $this->normalize($base),
            ];
        }, $files);

        foreach ($candidates as $cand) {
            $norm = $this->normalize($cand);
            foreach ($indexed as $it) {
                if ($it['norm'] === $norm) {
                    return $it['rel'];
                }
            }
        }

        foreach ($candidates as $cand) {
            $norm = $this->normalize($cand);
            foreach ($indexed as $it) {
                if (str_contains($it['norm'], $norm) || str_contains($norm, $it['norm'])) {
                    return $it['rel'];
                }
            }
        }

        $best = null;
        $bestPct = 0.0;
        foreach ($candidates as $cand) {
            $normCand = $this->normalize($cand);
            foreach ($indexed as $it) {
                similar_text($normCand, $it['norm'], $pct);
                if ($pct > $bestPct) {
                    $bestPct = $pct;
                    $best = $it['rel'];
                }
            }
        }

        return $bestPct >= 60 ? $best : null;
    }

    protected function nameCandidates(string $name): array
    {
        $cands = [$name];
        $noParen = trim(preg_replace('/\s*\(.*\)/', '', $name));
        if ($noParen !== '' && $noParen !== $name) {
            $cands[] = $noParen;
        }
        $first = strtok($noParen ?: $name, ' ');
        if ($first && $first !== $name) {
            $cands[] = $first;
        }

        return array_values(array_unique($cands));
    }

    protected function normalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = str_replace(['œ', 'æ'], ['oe', 'ae'], $value);
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($trans)) {
            $value = $trans;
        }
        $value = preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? $value;
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        return $value;
    }
}
