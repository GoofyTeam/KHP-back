<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\QuickAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickAccessControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
    }

    public function test_reset_creates_or_resets_defaults_for_company(): void
    {
        $this->actingAs($this->user);

        // Modifier les entrées existantes (créées au moment de la création de l'entreprise)
        $existing = QuickAccess::where('company_id', $this->company->id)->get();
        $existing->each(function (QuickAccess $qa, $idx) {
            $qa->update([
                'name' => 'Old '.($idx + 1),
                'icon' => 'Minus',
                'icon_color' => 'warning',
                'url_key' => 'old_'.($idx + 1),
            ]);
        });

        $response = $this->postJson('/api/quick-access/reset');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Quick access reset')
            ->assertJsonCount(5, 'quick_accesses');

        // Vérifier que les valeurs par défaut sont bien présentes
        $this->assertDatabaseHas('quick_accesses', [
            'company_id' => $this->company->id,
            'index' => 1,
            'name' => 'Add to stock',
            'icon' => 'Plus',
            'icon_color' => 'primary',
            'url_key' => 'add_to_stock',
        ]);
        $this->assertDatabaseHas('quick_accesses', [
            'company_id' => $this->company->id,
            'index' => 2,
            'name' => 'Menu Card',
            'icon' => 'Cutlery',
            'icon_color' => 'info',
            'url_key' => 'menu_card',
        ]);
        $this->assertDatabaseHas('quick_accesses', [
            'company_id' => $this->company->id,
            'index' => 3,
            'name' => 'Stock',
            'icon' => 'Check',
            'icon_color' => 'primary',
            'url_key' => 'stock',
        ]);
        $this->assertDatabaseHas('quick_accesses', [
            'company_id' => $this->company->id,
            'index' => 4,
            'name' => 'Waiters',
            'icon' => 'User',
            'icon_color' => 'info',
            'url_key' => 'waiters_page',
        ]);
        $this->assertDatabaseHas('quick_accesses', [
            'company_id' => $this->company->id,
            'index' => 5,
            'name' => 'Chefs',
            'icon' => 'ChefHat',
            'icon_color' => 'primary',
            'url_key' => 'chefs_page',
        ]);
    }

    public function test_update_bulk_updates_multiple_quick_accesses(): void
    {
        $this->actingAs($this->user);

        // Récupérer les 5 entrées créées lors de la création de l'entreprise
        $items = QuickAccess::where('company_id', $this->company->id)->orderBy('index')->get();

        $first = $items->first();
        $third = $items->get(2);

        $payload = [
            'quick_accesses' => [
                [
                    'id' => $first->id,
                    'name' => 'Updated One',
                    'icon' => 'Notebook',
                    'icon_color' => 'info',
                    'url_key' => 'menu_card',
                ],
                [
                    'id' => $third->id,
                    'name' => 'Updated Three',
                    'icon' => 'Check',
                    'icon_color' => 'primary',
                    'url_key' => 'stock',
                ],
            ],
        ];

        $response = $this->putJson('/api/quick-access', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Quick accesses updated')
            ->assertJsonCount(5, 'quick_accesses');

        // Vérifier les mises à jour en base
        $this->assertDatabaseHas('quick_accesses', [
            'id' => $first->id,
            'company_id' => $this->company->id,
            'name' => 'Updated One',
            'icon' => 'Notebook',
            'icon_color' => 'info',
            'url_key' => 'menu_card',
        ]);
        $this->assertDatabaseHas('quick_accesses', [
            'id' => $third->id,
            'company_id' => $this->company->id,
            'name' => 'Updated Three',
            'icon' => 'Check',
            'icon_color' => 'primary',
            'url_key' => 'stock',
        ]);

        // Ordre renvoyé par index croissant
        $data = $response->json('quick_accesses');
        $this->assertEquals([1, 2, 3, 4, 5], array_column($data, 'index'));
    }

    public function test_update_validates_icon_and_color_values(): void
    {
        $this->actingAs($this->user);

        $item = QuickAccess::where('company_id', $this->company->id)->orderBy('index')->first();

        $response = $this->putJson('/api/quick-access', [
            'quick_accesses' => [
                [
                    'id' => $item->id,
                    'icon' => 'UnknownIcon', // invalide
                    'icon_color' => 'weird',  // invalide
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'quick_accesses.0.icon',
                'quick_accesses.0.icon_color',
            ]);
    }
}
