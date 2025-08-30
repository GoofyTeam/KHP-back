<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Perishable;
use App\Services\ImageService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class IngredientSeeder extends Seeder
{
    private ImageService $imageService;

    /**
     * Dossier contenant tes images de seed
     * (relatif au disk "private" -> storage/app/private)
     */
    private string $seedImagesDir = 'seeders/images';

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function run(): void
    {
        // Charge une banque d'images locales (pas d'appels réseau)
        $images = $this->loadLocalImages(50); // prends large pour réutiliser partout
        if (empty($images)) {
            $this->command?->warn("Aucune image trouvée dans storage/app/private/{$this->seedImagesDir}");
        }

        $this->seedCompany('GoofyTeam', 15, $images);
        $this->seedOtherCompanies('GoofyTeam', 5, $images);
    }

    /**
     * Récupère N fichiers images du dossier privé et les transforme en UploadedFile.
     * Si $count > nb d'images dispo, on réutilise certaines images (tirage aléatoire).
     */
    private function loadLocalImages(int $count): array
    {
        // Essaye d’abord un disk "private" (recommandé), sinon fallback sur "local"
        $disk = Storage::disk('local');

        // Liste des fichiers dans le dossier
        $all = collect($disk->files($this->seedImagesDir))
            ->filter(function (string $path) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
            })
            ->values()
            ->all();

        if (empty($all)) {
            return [];
        }

        // Si on veut $count, on pioche avec remise si nécessaire
        $picked = [];
        for ($i = 0; $i < $count; $i++) {
            $relative = Arr::random($all);
            $absolute = method_exists($disk, 'path') ? $disk->path($relative) : storage_path('app/'.$relative);

            // Crée un UploadedFile "test" (pas un upload réel)
            $mime = @mime_content_type($absolute) ?: 'image/jpeg';

            $picked[] = new UploadedFile(
                $absolute,
                basename($absolute),
                $mime,
                null,
                true // test mode
            );
        }

        return $picked;
    }

    private function seedCompany(string $name, int $perLocation, array $images): void
    {
        $company = Company::where('name', $name)->firstOrFail();

        $ingredients = $this->createIngredients($company->id, $perLocation, $images);

        foreach ($ingredients as $ingredient) {
            $randomLocations = $company->locations->random(rand(1, $company->locations->count()));
            foreach ($randomLocations as $location) {
                $quantity = rand(1, 5) === 1 ? 0 : rand(0, 15) + (rand(50, 99) / 100);

                $ingredient->locations()->attach($location->id, [
                    // 1/5 out of stock, sinon entre 0.50 et 15.99
                    'quantity' => $quantity,
                ]);

                if ($quantity > 0) {
                    Perishable::create([
                        'ingredient_id' => $ingredient->id,
                        'location_id' => $location->id,
                        'company_id' => $company->id,
                        'quantity' => $quantity,
                    ]);
                }
            }
        }
    }

    private function seedOtherCompanies(string $exclude, int $perLocation, array $images): void
    {
        Company::where('name', '!=', $exclude)
            ->get()
            ->each(function (Company $company) use ($perLocation, $images) {
                $ingredients = $this->createIngredients($company->id, $perLocation, $images);

                foreach ($ingredients as $ingredient) {
                    $randomLocations = $company->locations->random(rand(1, $company->locations->count()));
                    foreach ($randomLocations as $location) {
                        $quantity = rand(1, 5) === 1 ? 0 : rand(0, 15) + (rand(50, 99) / 100);
                        $ingredient->locations()->attach($location->id, [
                            'quantity' => $quantity,
                        ]);

                        if ($quantity > 0) {
                            Perishable::create([
                                'ingredient_id' => $ingredient->id,
                                'location_id' => $location->id,
                                'company_id' => $company->id,
                                'quantity' => $quantity,
                            ]);
                        }
                    }
                }
            });
    }

    private function createIngredients(int $companyId, int $count, array $images): array
    {
        $ingredients = [];

        $categoryIds = Category::where('company_id', $companyId)
            ->pluck('id')
            ->all();

        for ($i = 0; $i < $count; $i++) {
            // pioche une image au hasard dans le pool
            /** @var \Illuminate\Http\UploadedFile $upload */
            $upload = $images[array_rand($images)];

            $ingredient = Ingredient::factory()->create([
                'company_id' => $companyId,
                'image_url' => $this->imageService->store($upload, 'ingredients'),
                'category_id' => Arr::random($categoryIds),
            ]);

            $ingredients[] = $ingredient;
        }

        return $ingredients;
    }
}
