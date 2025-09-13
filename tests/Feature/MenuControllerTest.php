<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * Class MenuControllerTest
 *
 * Use cases couverts :
 * - Commander un menu et annuler la commande avec impact stock
 * - Retirer le stock seulement après changement de statut si l'option est désactivée
 * - Obtenir les stats de commandes terminées entre deux dates via GraphQL
 * - Mettre à jour un menu en renvoyant la liste complète des items et des catégories
 */
class MenuControllerTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    /** Vérifie que la commande et l'annulation ajustent le stock. */
    public function test_order_and_cancel_menu_updates_stock(): void
    {
        $company = Company::factory()->create(['auto_complete_menu_orders' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id]);

        $ingredient->locations()->sync([$location->id => ['quantity' => 10]]);

        $category = MenuCategory::factory()->create(['company_id' => $company->id]);

        $menuPayload = [
            'name' => 'Test Menu',
            'type' => 'plat',
            'price' => 12.5,
            'category_ids' => [$category->id],
            'items' => [
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
            ->postJson('/api/menus', $menuPayload)
            ->assertStatus(201);

        $menu = Menu::where('name', 'Test Menu')->first();

        $response = $this->actingAs($user)
            ->postJson('/api/menus/'.$menu->id.'/command')
            ->assertStatus(201);

        $this->assertEquals(8, $ingredient->locations()->first()->pivot->quantity);

        $orderId = $response->json('order.id');

        $this->actingAs($user)
            ->postJson('/api/menus/command/'.$orderId.'/cancel')
            ->assertStatus(200);

        $this->assertEquals(10, $ingredient->fresh()->locations()->first()->pivot->quantity);
    }

    /** Vérifie que le stock n'est retiré qu'après passage à "completed" sans option. */
    public function test_order_needs_status_change_when_option_disabled(): void
    {
        $company = Company::factory()->create(['auto_complete_menu_orders' => false]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id]);

        $ingredient->locations()->sync([$location->id => ['quantity' => 5]]);

        $this->actingAs($user)
            ->postJson('/api/menus', [
                'name' => 'Pending Menu',
                'type' => 'plat',
                'price' => 8.5,
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

        $menu = Menu::where('name', 'Pending Menu')->first();

        $response = $this->actingAs($user)
            ->postJson('/api/menus/'.$menu->id.'/command')
            ->assertStatus(201);

        $this->assertEquals(5, $ingredient->fresh()->locations()->first()->pivot->quantity);

        $orderId = $response->json('order.id');

        $this->actingAs($user)
            ->putJson('/api/menus/command/'.$orderId.'/status', ['status' => 'completed'])
            ->assertStatus(200);

        $this->assertEquals(4, $ingredient->fresh()->locations()->first()->pivot->quantity);
    }

    /** Retourne le nombre de commandes terminées filtré par dates via GraphQL. */
    public function test_stats_between_dates_count_only_completed_orders(): void
    {
        $company = Company::factory()->create(['auto_complete_menu_orders' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id]);

        $ingredient->locations()->sync([$location->id => ['quantity' => 10]]);

        $menuPayload = [
            'name' => 'Stat Menu',
            'type' => 'plat',
            'price' => 10.0,
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
        ];

        $this->actingAs($user)
            ->postJson('/api/menus', $menuPayload)
            ->assertStatus(201);

        $menu = Menu::where('name', 'Stat Menu')->first();

        // Order within range
        $this->actingAs($user)
            ->postJson('/api/menus/'.$menu->id.'/command')
            ->assertStatus(201);

        // Order outside range
        MenuOrder::forceCreate([
            'menu_id' => $menu->id,
            'status' => 'completed',
            'quantity' => 1,
            'created_at' => Carbon::now()->subMonth(),
            'updated_at' => Carbon::now()->subMonth(),
        ]);

        $start = Carbon::now()->subDays(2)->toDateString();
        $end = Carbon::now()->addDay()->toDateString();

        $response = $this->actingAs($user)->graphQL(/** @lang GraphQL */ '
            query ($start: Date, $end: Date) {
                menuOrderStats(start: $start, end: $end) {
                    count
                }
            }
        ', ['start' => $start, 'end' => $end]);

        $response->assertJson([
            'data' => [
                'menuOrderStats' => ['count' => 1],
            ],
        ]);
    }

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
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id]);

        $location = Location::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->postJson('/api/menus', [
                'name' => 'Quantity Menu',
                'type' => 'plat',
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

    /** Vérifie qu'une commande échoue si le stock est insuffisant. */
    public function test_cannot_order_when_stock_insufficient(): void
    {
        $company = Company::factory()->create(['auto_complete_menu_orders' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id]);

        $ingredient->locations()->sync([$location->id => ['quantity' => 1]]);

        $this->actingAs($user)
            ->postJson('/api/menus', [
                'name' => 'Limited Menu',
                'type' => 'plat',
                'price' => 9.0,
                'category_ids' => [],
                'items' => [
                    [
                        'entity_id' => $ingredient->id,
                        'entity_type' => 'ingredient',
                        'quantity' => 2,
                        'unit' => 'unit',
                        'location_id' => $location->id,
                    ],
                ],
            ])
            ->assertStatus(201);

        $menu = Menu::where('name', 'Limited Menu')->first();

        $this->actingAs($user)
            ->postJson('/api/menus/'.$menu->id.'/command')
            ->assertStatus(422);
    }
}
