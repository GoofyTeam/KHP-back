<?php

namespace Database\Seeders;

use App\Enums\MeasurementUnit;
use App\Models\Category;
use App\Models\Company;
use App\Models\Ingredient;
use App\Services\ImageService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class IngredientSeeder extends Seeder
{
    private ImageService $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function run(): void
    {
        // Seed liste spécifique GoofyTeam
        $goofyTeam = Company::where('name', 'GoofyTeam')->firstOrFail();
        $this->seedGoofyTeamIngredients($goofyTeam);
    }

    /**
     * Seed la liste d'ingrédients spécifique pour GoofyTeam
     */
    protected function seedGoofyTeamIngredients(Company $company): void
    {
        // Map des catégories disponibles pour l'entreprise (nom => id)
        $categoriesByName = Category::where('company_id', $company->id)
            ->pluck('id', 'name')
            ->all();
        $fallbackCategoryId = $categoriesByName['Ingrédients Divers']
            ?? (reset($categoriesByName) ?: null);

        // Pré-liste les images locales disponibles pour matcher par nom
        $localImages = $this->listLocalImageFiles();

        $items = [
            // Produits carnés & poisson
            ['name' => 'Poitrine de poulet', 'qty' => 10.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '7290006739353'],
            ['name' => 'Entrecôte de bœuf', 'qty' => 8.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '20199845'],
            ['name' => 'Filet de porc', 'qty' => 6.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '3095759062017'],
            ['name' => 'Saumon frais (filet)', 'qty' => 7.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '20842161'],
            ['name' => 'Thon (frais ou surgelé)', 'qty' => 5.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '6111162000181'],
            ['name' => 'Moules', 'qty' => 8.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '8422107001308'],
            ['name' => 'Jambon cru', 'qty' => 2.5, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '8001665714587'],

            // Légumes & fruits frais
            ['name' => 'Pommes de terre', 'qty' => 20.0, 'unit' => MeasurementUnit::KILOGRAM],
            ['name' => 'Carottes', 'qty' => 10.0, 'unit' => MeasurementUnit::KILOGRAM],
            ['name' => 'Oignons jaunes', 'qty' => 8.0, 'unit' => MeasurementUnit::KILOGRAM],
            ['name' => 'Ail', 'qty' => 2.0, 'unit' => MeasurementUnit::KILOGRAM],
            ['name' => 'Poireaux', 'qty' => 5.0, 'unit' => MeasurementUnit::KILOGRAM],
            ['name' => 'Tomates fraîches', 'qty' => 10.0, 'unit' => MeasurementUnit::KILOGRAM],
            ['name' => 'Courgettes', 'qty' => 6.0, 'unit' => MeasurementUnit::KILOGRAM],
            ['name' => 'Salades (mélange, batavia, roquette)', 'qty' => 6.0, 'unit' => MeasurementUnit::KILOGRAM],
            ['name' => 'Citron', 'qty' => 5.0, 'unit' => MeasurementUnit::KILOGRAM],
            ['name' => 'Pommes', 'qty' => 8.0, 'unit' => MeasurementUnit::KILOGRAM],
            ['name' => 'Bananes', 'qty' => 6.0, 'unit' => MeasurementUnit::KILOGRAM],
            ['name' => 'Oranges', 'qty' => 8.0, 'unit' => MeasurementUnit::KILOGRAM],

            // Produits laitiers
            ['name' => 'Lait entier', 'qty' => 15.0, 'unit' => MeasurementUnit::LITRE, 'barcode' => '6111242100220'],
            ['name' => 'Crème fraîche', 'qty' => 6.0, 'unit' => MeasurementUnit::LITRE, 'barcode' => '7622300685782'],
            ['name' => 'Beurre doux', 'qty' => 6.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '3451790988677'],
            ['name' => 'Fromage râpé (emmental, parmesan)', 'qty' => 5.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '3228021170046'],
            ['name' => 'Fromages affinés (camembert, chèvre, bleu, etc.)', 'qty' => 5.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '3123930650064'],

            // Épicerie sèche
            ['name' => 'Pâtes (fusilli, penne, spaghetti)', 'qty' => 12.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '8076809529433'],
            ['name' => 'Riz basmati et riz arborio', 'qty' => 10.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '3038359007224'],
            ['name' => 'Lentilles vertes', 'qty' => 4.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '5051790270581'],
            ['name' => 'Farine T55', 'qty' => 15.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '3245414662926'],
            ['name' => 'Sucre semoule', 'qty' => 10.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '3165430520003'],
            ['name' => 'Sel fin', 'qty' => 3.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '3183280001800'],
            ['name' => 'Poivre noir en grains', 'qty' => 1.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '3166296203482'],
            ['name' => 'Huile d’olive', 'qty' => 10.0, 'unit' => MeasurementUnit::LITRE, 'barcode' => '4056489141877'],
            ['name' => 'Huile de tournesol', 'qty' => 5.0, 'unit' => MeasurementUnit::LITRE, 'barcode' => '3274080005003'],
            ['name' => 'Vinaigre de vin rouge', 'qty' => 3.0, 'unit' => MeasurementUnit::LITRE, 'barcode' => '3017620402678'],
            ['name' => 'Vinaigre balsamique', 'qty' => 3.0, 'unit' => MeasurementUnit::LITRE, 'barcode' => '8722700197508'],
            ['name' => 'Concentré de tomate', 'qty' => 5.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '6111021026000'],
            ['name' => 'Tomates pelées en conserve', 'qty' => 10.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '40879321'],

            // Produits surgelés
            ['name' => 'Légumes surgelés (haricots verts, épinards, petits pois)', 'qty' => 15.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '3083681116939'],
            ['name' => 'Frites surgelées', 'qty' => 20.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '8710438123692'],

            // Condiments & assaisonnements
            ['name' => 'Moutarde de Dijon', 'qty' => 2.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '8720182556738'],
            ['name' => 'Ketchup', 'qty' => 3.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '5449000131805'],
            ['name' => 'Mayonnaise', 'qty' => 3.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '8711200548002'],
            ['name' => 'Herbes de Provence', 'qty' => 500.0, 'unit' => MeasurementUnit::GRAM, 'barcode' => '3166291010610'],
            ['name' => 'Basilic frais', 'qty' => 1.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '3547130084540'],
            ['name' => 'Thym', 'qty' => 500.0, 'unit' => MeasurementUnit::GRAM, 'barcode' => '3011360005557'],
            ['name' => 'Laurier', 'qty' => 300.0, 'unit' => MeasurementUnit::GRAM, 'barcode' => '8722700217121'],
            ['name' => 'Paprika fumé', 'qty' => 500.0, 'unit' => MeasurementUnit::GRAM, 'barcode' => '3166296208869'],

            // Boissons & autres
            ['name' => 'Eau minérale', 'qty' => 50.0, 'unit' => MeasurementUnit::LITRE, 'barcode' => '3274080005003'],
            ['name' => 'Chocolat pâtissier', 'qty' => 4.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '0643435040823'],
            ['name' => 'Levure boulangère', 'qty' => 500.0, 'unit' => MeasurementUnit::GRAM, 'barcode' => '3564700440377'],
            // 20 douzaines = 240 unités
            ['name' => 'Œufs frais', 'qty' => 240.0, 'unit' => MeasurementUnit::UNIT, 'barcode' => '3245412846991'],
        ];

        foreach ($items as $item) {
            // Déduire une catégorie à partir du nom
            $catName = $this->guessCategoryNameForItem($item['name']);
            $categoryId = $categoriesByName[$catName] ?? $fallbackCategoryId;

            // Associe une image locale correspondant au nom
            $imageUrl = null;
            $matched = $this->findLocalImagePath($item['name'], $localImages);
            if ($matched) {
                try {
                    $absolute = method_exists(Storage::disk('local'), 'path')
                        ? Storage::disk('local')->path($matched)
                        : storage_path('app/'.$matched);
                    $mime = @mime_content_type($absolute) ?: 'image/jpeg';
                    $upload = new UploadedFile($absolute, basename($absolute), $mime, null, true);
                    $imageUrl = $this->imageService->store($upload, 'ingredients');
                } catch (\Throwable $e) {
                    // ignore, on tentera OFF plus bas si possible
                    $imageUrl = null;
                }
            }

            $ingredient = Ingredient::factory()->create([
                'company_id' => $company->id,
                'name' => $item['name'],
                'unit' => $item['unit']->value,
                'base_quantity' => $item['qty'],
                'image_url' => $imageUrl,
                'barcode' => $item['barcode'] ?? null,
                'category_id' => $categoryId,
            ]);

            // Met à jour une catégorie manquante
            if (empty($ingredient->category_id) && ! empty($categoryId)) {
                $ingredient->update(['category_id' => $categoryId]);
            }

            // Si pas d'image locale et qu'un code-barres est présent, tenter OFF
            $barcode = $ingredient->barcode;
            if (! empty($barcode) && empty($ingredient->image_url)) {
                $offUrl = "https://world.openfoodfacts.org/api/v0/product/{$barcode}.json";
                $response = Http::timeout(8)->get($offUrl);
                if ($response->successful()) {
                    $data = $response->json();
                    $img = $data['product']['image_front_url']
                        ?? $data['product']['image_url']
                        ?? null;

                    if (is_string($img) && filter_var($img, FILTER_VALIDATE_URL)) {
                        $storedPath = $this->imageService->storeFromUrl($img, 'ingredients');
                        $ingredient->update(['image_url' => $storedPath]);
                    }
                }
            }
        }
    }

    /**
     * Devine le nom de catégorie à partir du libellé d'ingrédient.
     */
    private function guessCategoryNameForItem(string $name): string
    {
        $n = $this->normalize($name);

        $checks = [
            'Viandes Rouges' => ['boeuf', 'bœuf', 'entrecote', 'entrecôte'],
            'Viandes Blanches' => ['poulet', 'porc'],
            'Charcuterie' => ['jambon'],
            'Poissons' => ['saumon', 'thon'],
            'Fruits de Mer' => ['moules'],

            'Légumes' => ['pomme de terre', 'pommes de terre', 'carotte', 'carottes', 'oignon', 'oignons', 'ail', 'poireau', 'poireaux', 'tomate', 'tomates', 'courgette', 'courgettes', 'salade', 'salades'],
            'Fruits' => ['pomme', 'pommes', 'banane', 'bananes', 'orange', 'oranges', 'citron', 'citrons'],

            'Fromages' => ['fromage', 'fromages', 'emmental', 'parmesan', 'camembert', 'chevre', 'chèvre', 'bleu'],
            'Produits Laitiers' => ['lait', 'creme', 'crème', 'beurre'],

            'Pâtes' => ['pates', 'pâtes', 'spaghetti', 'penne', 'fusilli'],
            'Riz' => ['riz'],
            'Légumineuses' => ['lentille', 'lentilles'],
            'Farines' => ['farine'],
            'Sucre et Édulcorants' => ['sucre'],
            'Huiles et Vinaigres' => ['huile', 'vinaigre'],
            'Produits en Conserves' => ['conserve', 'pel', 'pelées', 'pelée', 'concentre', 'concentré'],

            'Produits Surgelés' => ['surgel', 'surgelés', 'surgelé'],

            'Condiments et Sauces' => ['ketchup', 'mayonnaise', 'moutarde'],
            'Épices et Herbes' => ['herbes', 'basilic', 'thym', 'laurier', 'paprika', 'curry', 'cumin', 'cannelle', 'piment'],

            'Pains et Viennoiseries' => ['pain', 'baguette', 'baguettes', 'croissant', 'croissants', 'mie'],

            'Eaux Minérales' => ['eau minerale', 'eau minérale'],
            'Jus de Fruits' => ['jus'],
            'Café et Thé' => ['cafe', 'café', 'the', 'thé'],
            'Chocolat et Cacao' => ['chocolat'],
            'Œufs' => ['oeuf', 'oeufs', 'œuf', 'œufs'],
        ];

        foreach ($checks as $category => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($n, $this->normalize($needle))) {
                    return $category;
                }
            }
        }

        // fallback plus générique
        return 'Ingrédients Divers';
    }

    /**
     * Liste les images locales possibles dans storage.
     */
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

    /**
     * Trouve une image locale qui correspond au nom donné.
     */
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

        // 1) égalité exacte normalisée
        foreach ($candidates as $cand) {
            $norm = $this->normalize($cand);
            foreach ($indexed as $it) {
                if ($it['norm'] === $norm) {
                    return $it['rel'];
                }
            }
        }

        // 2) inclusion dans un sens ou l'autre
        foreach ($candidates as $cand) {
            $norm = $this->normalize($cand);
            foreach ($indexed as $it) {
                if (str_contains($it['norm'], $norm) || str_contains($norm, $it['norm'])) {
                    return $it['rel'];
                }
            }
        }

        // 3) meilleure correspondance fuzzy
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
