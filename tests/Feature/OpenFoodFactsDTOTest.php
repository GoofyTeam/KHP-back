<?php

namespace Tests\Feature;

use App\DTO\OpenFoodFactsDTO;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenFoodFactsDTOTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_name_uses_company_language(): void
    {
        $company = Company::factory()->create(['open_food_facts_language' => 'en']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $this->actingAs($user);

        $dto = new OpenFoodFactsDTO([
            'code' => '123456',
            'product_name_fr' => 'Nom FR',
            'product_name_en' => 'Name EN',
        ]);

        $this->assertSame('Name EN', $dto->product_name);
    }
}
