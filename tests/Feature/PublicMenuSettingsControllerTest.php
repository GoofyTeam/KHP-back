<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMenuSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_update_public_menu_settings(): void
    {
        $company = Company::factory()->create([
            'show_out_of_stock_menus_on_card' => true,
            'show_menu_images' => true,
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)
            ->putJson('/api/public-menus', [
                'public_menu_card_url' => ' Le Resto ! ',
                'only_sufficient_stock' => true,
                'with_pictures' => false,
            ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.public_menu_card_url', 'le-resto')
            ->assertJsonPath('data.only_sufficient_stock', true)
            ->assertJsonPath('data.with_pictures', false);

        $company->refresh();

        $this->assertSame('le-resto', $company->public_menu_card_url);
        $this->assertFalse($company->show_out_of_stock_menus_on_card);
        $this->assertFalse($company->show_menu_images);
    }

    public function test_public_menu_card_url_must_be_unique(): void
    {
        $company = Company::factory()->create(['public_menu_card_url' => 'unique-url']);
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $otherCompany->id]);

        $this->actingAs($user)
            ->putJson('/api/public-menus', [
                'public_menu_card_url' => 'unique-url',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.public_menu_card_url.0', 'ALREADY_TAKEN, désolé mais cette URL est déjà prise');
    }

    public function test_public_menu_card_url_cannot_be_empty(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->putJson('/api/public-menus', [
                'public_menu_card_url' => null,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['public_menu_card_url']);

        $this->actingAs($user)
            ->putJson('/api/public-menus', [
                'public_menu_card_url' => '   ',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['public_menu_card_url']);
    }

    public function test_can_toggle_with_pictures_without_touching_other_flags(): void
    {
        $company = Company::factory()->create([
            'show_out_of_stock_menus_on_card' => false,
            'show_menu_images' => true,
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->putJson('/api/public-menus', [
                'with_pictures' => false,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.with_pictures', false)
            ->assertJsonPath('data.only_sufficient_stock', true);

        $company->refresh();

        $this->assertFalse($company->show_menu_images);
        $this->assertFalse($company->show_out_of_stock_menus_on_card);
    }
}
