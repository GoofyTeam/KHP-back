<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\OpenFoodFactsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenFoodFactsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_uses_company_language(): void
    {
        Http::fake();

        $company = Company::factory()->create(['open_food_facts_language' => 'en']);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        $service = app(OpenFoodFactsService::class);
        $service->searchByBarcode('123456');

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://world.openfoodfacts.org')
            && str_contains($request->url(), 'lc=en'));
    }
}
