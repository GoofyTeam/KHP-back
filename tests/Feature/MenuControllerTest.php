<?php

namespace Tests\Feature;

use App\Enums\MenuServiceType;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Class MenuControllerTest
 *
 * Use cases couverts :
 * - Créer un menu et remplacer l'ensemble de ses items
 * - Mettre à jour les catégories associées
 * - Empêcher la duplication d'items dans un menu
 */
class MenuControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Vérifie que l'on peut remplacer la liste des items d'un menu. */
    public function test_update_menu_replaces_items(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ingredientA = Ingredient::factory()->create(['company_id' => $company->id]);
        $ingredientB = Ingredient::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);

        // Création initiale avec un seul ingrédient
        $this->actingAs($user)
            ->postJson('/api/menus', [
                'name' => 'Incremental Menu',
                'type' => 'plat',
                'service_type' => MenuServiceType::DIRECT->value,
                'is_returnable' => false,
                'price' => 9.0,
                'category_ids' => [],
                'items' => [
                    [
                        'entity_id' => $ingredientA->id,
                        'entity_type' => 'ingredient',
                        'quantity' => 1,
                        'unit' => 'unit',
                        'location_id' => $location->id,
                    ],
                ],
            ])
            ->assertStatus(201);

        $menu = Menu::where('name', 'Incremental Menu')->first();
        $this->assertSame(MenuServiceType::DIRECT, $menu->service_type);
        $this->assertFalse($menu->is_returnable);

        // Remplacement par deux ingrédients
        $this->actingAs($user)
            ->putJson('/api/menus/'.$menu->id, [
                'items' => [
                    [
                        'entity_id' => $ingredientA->id,
                        'entity_type' => 'ingredient',
                        'quantity' => 1,
                        'unit' => 'unit',
                        'location_id' => $location->id,
                    ],
                    [
                        'entity_id' => $ingredientB->id,
                        'entity_type' => 'ingredient',
                        'quantity' => 2,
                        'unit' => 'unit',
                        'location_id' => $location->id,
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonCount(2, 'menu.items');

        // Remplacement par un seul ingrédient
        $this->actingAs($user)
            ->putJson('/api/menus/'.$menu->id, [
                'items' => [
                    [
                        'entity_id' => $ingredientB->id,
                        'entity_type' => 'ingredient',
                        'quantity' => 2,
                        'unit' => 'unit',
                        'location_id' => $location->id,
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonCount(1, 'menu.items');
    }

    /** Vérifie que l'on peut modifier la quantité d'un item existant en renvoyant la liste complète. */
    public function test_update_menu_modifies_item_quantity(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id, 'unit' => 'unit']);

        $location = Location::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->postJson('/api/menus', [
                'name' => 'Quantity Menu',
                'type' => 'plat',
                'service_type' => MenuServiceType::PREP->value,
                'is_returnable' => true,
                'price' => 9.0,
                'category_ids' => [],
                'items' => [
                    [
                        'entity_id' => $ingredient->id,
                        'entity_type' => 'ingredient',
                        'quantity' => 1,
                        'unit' => 'unit',
                        'location_id' => $location->id,
                    ],
                ],
            ])
            ->assertStatus(201);

        $menu = Menu::where('name', 'Quantity Menu')->first();
        $this->assertSame(MenuServiceType::PREP, $menu->service_type);
        $this->assertTrue($menu->is_returnable);

        $this->actingAs($user)
            ->putJson('/api/menus/'.$menu->id, [
                'items' => [
                    [
                        'entity_id' => $ingredient->id,
                        'entity_type' => 'ingredient',
                        'quantity' => 5,
                        'unit' => 'unit',
                        'location_id' => $location->id,
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('menu.items.0.quantity', 5);
    }

    /** Permet de remplacer les catégories d'un menu. */
    public function test_can_replace_categories(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);

        $categoryA = MenuCategory::factory()->create(['company_id' => $company->id]);
        $categoryB = MenuCategory::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->postJson('/api/menus', [
                'name' => 'Cat Menu',
                'type' => 'plat',
                'service_type' => MenuServiceType::DIRECT->value,
                'is_returnable' => false,
                'price' => 9.0,
                'category_ids' => [$categoryA->id],
                'items' => [
                    [
                        'entity_id' => $ingredient->id,
                        'entity_type' => 'ingredient',
                        'quantity' => 1,
                        'unit' => 'unit',
                        'location_id' => $location->id,
                    ],
                ],
            ])
            ->assertStatus(201);

        $menu = Menu::where('name', 'Cat Menu')->first();

        $this->actingAs($user)
            ->putJson('/api/menus/'.$menu->id, [
                'category_ids' => [$categoryB->id],
                'items' => [
                    [
                        'entity_id' => $ingredient->id,
                        'entity_type' => 'ingredient',
                        'quantity' => 1,
                        'unit' => 'unit',
                        'location_id' => $location->id,
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonCount(1, 'menu.categories')
            ->assertJsonPath('menu.categories.0.id', $categoryB->id);
    }

    /** Empêche d'avoir deux fois le même item dans un menu. */
    public function test_menu_cannot_have_duplicate_items(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id]);

        $location = Location::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Dup Menu',
            'type' => 'plat',
            'service_type' => MenuServiceType::DIRECT->value,
            'is_returnable' => false,
            'price' => 9.0,
            'category_ids' => [],
            'items' => [
                [
                    'entity_id' => $ingredient->id,
                    'entity_type' => 'ingredient',
                    'quantity' => 1,
                    'unit' => 'unit',
                    'location_id' => $location->id,
                ],
                [
                    'entity_id' => $ingredient->id,
                    'entity_type' => 'ingredient',
                    'quantity' => 2,
                    'unit' => 'unit',
                    'location_id' => $location->id,
                ],
            ],
        ];

        $this->actingAs($user)
            ->postJson('/api/menus', $payload)
            ->assertStatus(422);
    }
}
