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

        $response = $this->actingAs($user)
            ->putJson('/api/company/options', [
                'auto_complete_menu_orders' => true,
            ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.auto_complete_menu_orders', true);

        $company->refresh();

        $this->assertTrue($company->auto_complete_menu_orders);
        $this->assertSame(
            sprintf('%d-%s', $company->id, Str::slug($company->name)),
            $company->public_card_url
        );
        $this->assertFalse($company->show_out_of_stock_menus);
        $this->assertTrue($company->show_menu_images);
    }

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
            $company->public_card_url
        );
        $this->assertFalse($company->show_out_of_stock_menus);
        $this->assertTrue($company->show_menu_images);
    }
}
