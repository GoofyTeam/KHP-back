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

    private string $picsumBase = 'https://picsum.photos/200';

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function run(): void
    {
        $images = $this->fetchRandomImages(count: 15);

        $this->seedCompany('GoofyTeam', 15, $images);

        $this->seedOtherCompanies('GoofyTeam', 5, $images);
    }

    private function fetchRandomImages(int $count): array
    {
        $uploads = [];

        for ($i = 0; $i < $count; $i++) {
            $response = Http::get($this->picsumBase);
            $tempFile = $this->storeTemporaryFile($response);

            $uploads[] = new UploadedFile(
                $tempFile,
                basename($tempFile),
                $response->header('Content-Type'),
                null,
                true
            );
        }

        return $uploads;
    }

    private function storeTemporaryFile($response): string
    {
        $pathInfo = pathinfo(parse_url($response->effectiveUri(), PHP_URL_PATH));
        $filename = $pathInfo['filename'].'.'.($pathInfo['extension'] ?? 'jpg');
        $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.$filename;

        file_put_contents($tempPath, $response->body());

        return $tempPath;
    }

    private function seedCompany(string $companyName, int $perLocation, array $images): void
    {
        $company = Company::where('name', $companyName)->firstOrFail();

        foreach ($company->locations as $location) {
            $this->createIngredients($company->id, $location->id, $perLocation, $images);
        }
    }

    private function seedOtherCompanies(string $excludeName, int $perLocation, array $images): void
    {
        Company::where('name', '!=', $excludeName)
            ->get()
            ->each(
                fn ($company) => $company->locations->each(
                    fn ($location) => $this->createIngredients($company->id, $location->id, $perLocation, $images)
                )
            );
    }

    private function createIngredients(int $companyId, int $locationId, int $count, array $images): void
    {
        for ($i = 0; $i < $count; $i++) {
            $upload = $images[array_rand($images)];

            $ingredient = Ingredient::factory()->create([
                'company_id' => $companyId,
                'image_url' => $this->imageService->store($upload, 'ingredients'),
            ]);

            $category = Category::inRandomOrder()->first();
            $ingredient->categories()->attach($category->id);

            $ingredient->locations()->attach($locationId);
        }
    }
}
