<?php

namespace Database\Seeders;

use App\Enums\MeasurementUnit;
use App\Models\Category;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Preparation;
use App\Models\PreparationEntity;
use App\Services\ImageService;
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
        $this->seedGoofyTeamSpecificPreparations($company, $localImages);
    }

    /**
     * Crée 10 préparations nommées en utilisant exclusivement des ingrédients
     * présents dans la liste de GoofyTeam (IngredientSeeder) et des catégories existantes.
     */
    private function seedGoofyTeamSpecificPreparations(Company $company, array $localImages): void
    {
        $categories = Category::where('company_id', $company->id)->pluck('id', 'name')->all();
        // Liste des emplacements pour pouvoir y attribuer des stocks initiaux
        $locationIds = $company->locations()->pluck('id')->all();

        $recipes = [
            [
                'name' => 'Salade de tomates au basilic',
                'category' => 'Salades',
                'unit' => MeasurementUnit::UNIT,
                'ingredients' => ['Tomates fraîches', 'Basilic frais', 'Huile d’olive', 'Sel fin'],
            ],
            [
                'name' => 'Soupe poireaux-pommes de terre',
                'category' => 'Soupes',
                'unit' => MeasurementUnit::LITRE,
                'ingredients' => ['Poireaux', 'Pommes de terre', 'Crème fraîche', 'Sel fin'],
            ],
            [
                'name' => 'Purée de pommes de terre',
                'category' => 'Plats Préparés',
                'unit' => MeasurementUnit::KILOGRAM,
                'ingredients' => ['Pommes de terre', 'Beurre doux', 'Lait entier', 'Sel fin'],
            ],
            [
                'name' => 'Ratatouille express',
                'category' => 'Plats Préparés',
                'unit' => MeasurementUnit::KILOGRAM,
                'ingredients' => ['Courgettes', 'Tomates fraîches', 'Oignons jaunes', 'Ail', 'Huile d’olive'],
            ],
            [
                'name' => 'Poulet citron ail',
                'category' => 'Plats Préparés',
                'unit' => MeasurementUnit::KILOGRAM,
                'ingredients' => ['Poitrine de poulet', 'Citron', 'Ail', 'Huile d’olive', 'Poivre noir en grains', 'Sel fin'],
            ],
            [
                'name' => 'Saumon grillé au citron',
                'category' => 'Plats Préparés',
                'unit' => MeasurementUnit::KILOGRAM,
                'ingredients' => ['Saumon frais (filet)', 'Citron', 'Huile d’olive', 'Sel fin'],
            ],
            [
                'name' => 'Spaghetti tomate-basilic',
                'category' => 'Plats Préparés',
                'unit' => MeasurementUnit::KILOGRAM,
                'ingredients' => ['Pâtes (fusilli, penne, spaghetti)', 'Tomates pelées en conserve', 'Basilic frais', 'Ail', 'Huile d’olive', 'Sel fin'],
            ],
            [
                'name' => 'Œufs brouillés',
                'category' => 'Plats Préparés',
                'unit' => MeasurementUnit::UNIT,
                'ingredients' => ['Œufs frais', 'Beurre doux', 'Sel fin', 'Poivre noir en grains'],
            ],
            [
                'name' => 'Lentilles au curry',
                'category' => 'Plats Préparés',
                'unit' => MeasurementUnit::KILOGRAM,
                'ingredients' => ['Lentilles vertes', 'Oignons jaunes', 'Curry', 'Ail', 'Huile de tournesol', 'Sel fin'],
            ],
            [
                'name' => 'Bananes caramélisées',
                'category' => 'Desserts et Pâtisseries',
                'unit' => MeasurementUnit::UNIT,
                'ingredients' => ['Bananes', 'Sucre semoule', 'Beurre doux'],
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
                'image_url' => $imageUrl,
            ]);

            // Lier les ingrédients par nom s'ils existent pour la société
            $ingredientIds = Ingredient::where('company_id', $company->id)
                ->whereIn('name', $recipe['ingredients'])
                ->pluck('id', 'name')
                ->all();

            foreach ($recipe['ingredients'] as $ingName) {
                if (! isset($ingredientIds[$ingName])) {
                    continue;
                }
                PreparationEntity::firstOrCreate([
                    'preparation_id' => $prep->id,
                    'entity_id' => $ingredientIds[$ingName],
                    'entity_type' => Ingredient::class,
                ]);
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
