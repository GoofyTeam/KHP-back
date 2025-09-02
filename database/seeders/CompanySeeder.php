<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Ingredient;
use App\Enums\MeasurementUnit;
use App\Services\OpenFoodFactsService;
use App\Services\ImageService;
use App\DTO\OpenFoodFactsDTO;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    private OpenFoodFactsService $offService;
    private ImageService $imageService;

    public function __construct(OpenFoodFactsService $offService, ImageService $imageService)
    {
        $this->offService = $offService;
        $this->imageService = $imageService;
    }
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure the main company exists
        $goofyTeam = Company::firstOrCreate(['name' => 'GoofyTeam']);

        // Create additional companies if needed
        Company::factory()->count(9)->create();

        // Seed the specific list of ingredients for GoofyTeam
        $this->seedGoofyTeamIngredients($goofyTeam);
    }

    /**
     * Seed the provided list of ingredients for the given company.
     */
    protected function seedGoofyTeamIngredients(Company $company): void
    {
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
            ['name' => 'Huile de tournesol', 'qty' => 5.0, 'unit' => MeasurementUnit::LITRE, 'barcode' => '3265471024086'],
            ['name' => 'Vinaigre balsamique', 'qty' => 3.0, 'unit' => MeasurementUnit::LITRE, 'barcode' => '8722700197508'],

            // Produits conserves & divers
            ['name' => 'Tomates pelées en boîte', 'qty' => 12.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '3256228969187'],
            ['name' => 'Champignons émincés en boîte', 'qty' => 4.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '3256222115528'],
            ['name' => 'Olives vertes et noires', 'qty' => 3.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '7610058207714'],
            ['name' => 'Bouillon (volaille, bœuf, légumes)', 'qty' => 2.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '50160884'],
            ['name' => 'Moutarde de Dijon', 'qty' => 2.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '8720182556738'],
            ['name' => 'Mayonnaise', 'qty' => 3.0, 'unit' => MeasurementUnit::LITRE, 'barcode' => '8722700479475'],

            // Boulangerie & pâtisserie
            ['name' => 'Pain (baguette, pain de campagne)', 'qty' => 200.0, 'unit' => MeasurementUnit::UNIT, 'barcode' => '4056489034407'],
            ['name' => 'Chocolat pâtissier', 'qty' => 4.0, 'unit' => MeasurementUnit::KILOGRAM, 'barcode' => '0643435040823'],
            ['name' => 'Levure boulangère', 'qty' => 500.0, 'unit' => MeasurementUnit::GRAM, 'barcode' => '3564700440377'],
            // 20 douzaines = 240 unités
            ['name' => 'Œufs frais', 'qty' => 240.0, 'unit' => MeasurementUnit::UNIT, 'barcode' => '3245412846991'],
        ];

        // Preload available local images
        $localImages = $this->listLocalImageFiles();

        foreach ($items as $item) {
            $ingredient = Ingredient::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $item['name'],
                ],
                [
                    'unit' => $item['unit']->value,
                    'base_quantity' => $item['qty'],
                    'image_url' => null,
                    'barcode' => $item['barcode'] ?? null,
                ]
            );

            // If the ingredient already existed without a barcode, update it
            if (empty($ingredient->barcode) && ! empty($item['barcode'])) {
                $ingredient->update(['barcode' => $item['barcode']]);
            }

            // Try to fetch and store image from OpenFoodFacts when a barcode is provided
            if (! empty($item['barcode']) && empty($ingredient->image_url)) {
                try {
                    $data = $this->offService->searchByBarcode($item['barcode']);
                    if (! empty($data)) {
                        $dto = new OpenFoodFactsDTO($data);
                        if (! empty($dto->imageUrl)) {
                            $path = $this->imageService->storeFromUrl($dto->imageUrl, 'ingredients');
                            $ingredient->update(['image_url' => $path]);
                        }
                    }
                } catch (\Throwable $e) {
                    // Skip image on failure; keep seeding robust
                    $this->command?->warn('Image OFF non récupérée pour "'.$item['name'].'" ('.$item['barcode'].')');
                }
            }

            // For items without barcode, try to attach a local image by name
            if (empty($item['barcode']) && empty($ingredient->image_url)) {
                $matched = $this->findLocalImagePath($item['name'], $localImages);
                if ($matched) {
                    try {
                        $absolute = method_exists(Storage::disk('local'), 'path')
                            ? Storage::disk('local')->path($matched)
                            : storage_path('app/'.$matched);
                        $mime = @mime_content_type($absolute) ?: 'image/jpeg';
                        $upload = new UploadedFile($absolute, basename($absolute), $mime, null, true);
                        $storedPath = $this->imageService->store($upload, 'ingredients');
                        $ingredient->update(['image_url' => $storedPath]);
                    } catch (\Throwable $e) {
                        $this->command?->warn('Image locale non affectée pour "'.$item['name'].'"');
                    }
                } else {
                    $this->command?->warn('Aucune image locale trouvée pour "'.$item['name'].'"');
                }
            }
        }
    }

    /**
     * List available local images from storage.
     * Searches in storage/app/private/seeders/images then storage/app/seeders/images.
     * Returns relative paths.
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
     * Try to find a local image path that matches the provided ingredient name.
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

        // 1) Exact normalized match
        foreach ($candidates as $cand) {
            $norm = $this->normalize($cand);
            foreach ($indexed as $it) {
                if ($it['norm'] === $norm) {
                    return $it['rel'];
                }
            }
        }

        // 2) Contains match either way
        foreach ($candidates as $cand) {
            $norm = $this->normalize($cand);
            foreach ($indexed as $it) {
                if (str_contains($it['norm'], $norm) || str_contains($norm, $it['norm'])) {
                    return $it['rel'];
                }
            }
        }

        // 3) Fuzzy best match
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
