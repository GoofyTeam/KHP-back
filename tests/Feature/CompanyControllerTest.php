<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Class CompanyControllerTest
 *
 * Use cases couverts :
 * - Mettre à jour la langue Open Food Facts de l'entreprise
 */
class CompanyControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Vérifie que la langue d'Open Food Facts peut être modifiée. */
    public function test_update_open_food_facts_language(): void
    {
        $company = Company::factory()->create(['open_food_facts_language' => 'fr']);
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)
            ->putJson('/api/company/options', [
                'open_food_facts_language' => 'en',
            ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.open_food_facts_language', 'en');

        $company->refresh();

        $this->assertSame('en', $company->open_food_facts_language);
        $this->assertSame(
            sprintf('%d-%s', $company->id, Str::slug($company->name)),
            $company->public_menu_card_url
        );
        $this->assertFalse($company->show_out_of_stock_menus_on_card);
        $this->assertTrue($company->show_menu_images);
    }
}
