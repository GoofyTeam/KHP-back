<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use App\Models\Ingredient;
use App\Services\ImageService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class IngredientSeeder extends Seeder
{
    private ImageService $imageService;

    private string $picsumUrl = 'https://picsum.photos/200/200.jpg';

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function run(): void
    {
        $images = $this->fetchRandomImages(15);
        $this->seedCompany('GoofyTeam', 15, $images);
        $this->seedOtherCompanies('GoofyTeam', 5, $images);
    }

    private function fetchRandomImages(int $count): array
    {
        $uploads = [];

        for ($i = 0; $i < $count; $i++) {

            $response = Http::get($this->picsumUrl.'?random='.uniqid());

            $tempFile = $this->storeTemporaryFile($response);

            $uploads[] = new UploadedFile(
                $tempFile,
                basename($tempFile),
                'image/jpeg',
                null,
                true
            );
        }

        return $uploads;
    }

    private function storeTemporaryFile($response): string
    {
        $filename = uniqid('picsum_').'.jpg';
        $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.$filename;
        file_put_contents($tempPath, $response->body());

        return $tempPath;
    }

    private function seedCompany(string $name, int $perLocation, array $images): void
    {
        $company = Company::where('name', $name)->firstOrFail();

        $ingredients = $this->createIngredients($company->id, $perLocation, $images);

        foreach ($ingredients as $ingredient) {
            $randomLocations = $company->locations->random(rand(1, $company->locations->count()));
            foreach ($randomLocations as $location) {
                $ingredient->locations()->attach($location->id, [
                    'quantity' => rand(1, 5) === 1 ? 0 : rand(0, 15) + (rand(50, 99) / 100), // 1/5 chance d'être out of stock, sinon entre 0.50 et 15.99
                ]);
            }
        }
    }

    private function seedOtherCompanies(string $exclude, int $perLocation, array $images): void
    {
        Company::where('name', '!=', $exclude)
            ->get()
            ->each(
                function (Company $company) use ($perLocation, $images) {
                    $ingredients = $this->createIngredients($company->id, $perLocation, $images);

                    foreach ($ingredients as $ingredient) {
                        $randomLocations = $company->locations->random(rand(1, $company->locations->count()));
                        foreach ($randomLocations as $location) {
                            $ingredient->locations()->attach($location->id, [
                                'quantity' => rand(1, 5) === 1 ? 0 : rand(0, 15) + (rand(50, 99) / 100), // 1/5 chance d'être out of stock, sinon entre 0.50 et 15.99
                            ]);
                        }
                    }
                }
            );
    }

    private function createIngredients(int $companyId, int $count, array $images): array
    {
        $ingredients = [];

        for ($i = 0; $i < $count; $i++) {
            $upload = $images[array_rand($images)];

            $ingredient = Ingredient::factory()->create([
                'company_id' => $companyId,
                'image_url' => $this->imageService->store($upload, 'ingredients'),
            ]);

            $cat = Category::inRandomOrder()->first();
            $ingredient->categories()->attach($cat->id);

            $ingredients[] = $ingredient;
        }

        return $ingredients;
    }
}
