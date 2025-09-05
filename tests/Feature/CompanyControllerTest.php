<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Class CompanyControllerTest
 *
 * Use cases couverts :
 * - Mettre à jour les options de l'entreprise
 */
class CompanyControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Vérifie qu'une entreprise peut activer la complétion automatique des commandes de menu. */
    public function test_update_auto_complete_menu_orders(): void
    {
        $company = Company::factory()->create(['auto_complete_menu_orders' => false]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->putJson('/api/company/options', [
                'auto_complete_menu_orders' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.auto_complete_menu_orders', true);

        $this->assertTrue($company->fresh()->auto_complete_menu_orders);
    }

    /** Vérifie que la langue d'Open Food Facts peut être modifiée. */
    public function test_update_open_food_facts_language(): void
    {
        $company = Company::factory()->create(['open_food_facts_language' => 'fr']);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->putJson('/api/company/options', [
                'open_food_facts_language' => 'en',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.open_food_facts_language', 'en');

        $this->assertSame('en', $company->fresh()->open_food_facts_language);
    }
}
