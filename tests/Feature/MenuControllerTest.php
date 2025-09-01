<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Menu;
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
 * - Obtenir les stats de commandes terminées entre deux dates via GraphQL
 * - Mettre à jour un menu en ajoutant, retirant ou modifiant des items
 */
class MenuControllerTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    /** Vérifie que la commande et l'annulation ajustent le stock. */
    public function test_order_and_cancel_menu_updates_stock(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id]);

        $ingredient->locations()->sync([$location->id => ['quantity' => 10]]);

        $menuPayload = [
            'name' => 'Test Menu',
            'items' => [
                [
                    'entity_id' => $ingredient->id,
                    'entity_type' => 'ingredient',
                    'quantity' => 2,
                    'unit' => 'unit',
                ],
            ],
        ];

        $this->actingAs($user)
            ->postJson('/api/menus', $menuPayload)
            ->assertStatus(201);

        $menu = Menu::where('name', 'Test Menu')->first();

        $orderPayload = [
            'location_id' => $location->id,
            'status' => 'completed',
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/menus/'.$menu->id.'/command', $orderPayload)
            ->assertStatus(201);

        $this->assertEquals(8, $ingredient->locations()->first()->pivot->quantity);

        $orderId = $response->json('order.id');

        $this->actingAs($user)
            ->postJson('/api/menus/command/'.$orderId.'/cancel')
            ->assertStatus(200);

        $this->assertEquals(10, $ingredient->fresh()->locations()->first()->pivot->quantity);
    }

    /** Retourne le nombre de commandes terminées filtré par dates via GraphQL. */
    public function test_stats_between_dates_count_only_completed_orders(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id]);

        $ingredient->locations()->sync([$location->id => ['quantity' => 10]]);

        $menuPayload = [
            'name' => 'Stat Menu',
            'items' => [
                [
                    'entity_id' => $ingredient->id,
                    'entity_type' => 'ingredient',
                    'quantity' => 1,
                    'unit' => 'unit',
                ],
            ],
        ];

        $this->actingAs($user)
            ->postJson('/api/menus', $menuPayload)
            ->assertStatus(201);

        $menu = Menu::where('name', 'Stat Menu')->first();

        // Order within range
        $this->actingAs($user)
            ->postJson('/api/menus/'.$menu->id.'/command', [
                'location_id' => $location->id,
                'status' => 'completed',
            ])
            ->assertStatus(201);

        // Order outside range
        MenuOrder::create([
            'menu_id' => $menu->id,
            'location_id' => $location->id,
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

    /** Vérifie que l'on peut ajouter ou retirer des items sans renvoyer la liste complète. */
    public function test_update_menu_adds_and_removes_items_incrementally(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ingredientA = Ingredient::factory()->create(['company_id' => $company->id]);
        $ingredientB = Ingredient::factory()->create(['company_id' => $company->id]);

        // Création initiale avec un seul ingrédient
        $this->actingAs($user)
            ->postJson('/api/menus', [
                'name' => 'Incremental Menu',
                'items' => [
                    [
                        'entity_id' => $ingredientA->id,
                        'entity_type' => 'ingredient',
                        'quantity' => 1,
                        'unit' => 'unit',
                    ],
                ],
            ])
            ->assertStatus(201);

        $menu = Menu::where('name', 'Incremental Menu')->first();

        // Ajout d'un nouvel ingrédient sans renvoyer le premier
        $this->actingAs($user)
            ->putJson('/api/menus/'.$menu->id, [
                'items_to_add' => [
                    [
                        'entity_id' => $ingredientB->id,
                        'entity_type' => 'ingredient',
                        'quantity' => 2,
                        'unit' => 'unit',
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonCount(2, 'menu.items');

        // Suppression du premier ingrédient sans renvoyer le second
        $this->actingAs($user)
            ->putJson('/api/menus/'.$menu->id, [
                'items_to_remove' => [
                    [
                        'entity_id' => $ingredientA->id,
                        'entity_type' => 'ingredient',
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonCount(1, 'menu.items');
    }

    /** Vérifie que l'on peut modifier la quantité d'un item existant sans renvoyer la liste complète. */
    public function test_update_menu_modifies_item_quantity(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->postJson('/api/menus', [
                'name' => 'Quantity Menu',
                'items' => [
                    [
                        'entity_id' => $ingredient->id,
                        'entity_type' => 'ingredient',
                        'quantity' => 1,
                        'unit' => 'unit',
                    ],
                ],
            ])
            ->assertStatus(201);

        $menu = Menu::where('name', 'Quantity Menu')->first();

        $this->actingAs($user)
            ->putJson('/api/menus/'.$menu->id, [
                'items_to_update' => [
                    [
                        'entity_id' => $ingredient->id,
                        'entity_type' => 'ingredient',
                        'quantity' => 5,
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('menu.items.0.quantity', 5);
    }

    /** Empêche d'avoir deux fois le même item dans un menu. */
    public function test_menu_cannot_have_duplicate_items(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Dup Menu',
            'items' => [
                [
                    'entity_id' => $ingredient->id,
                    'entity_type' => 'ingredient',
                    'quantity' => 1,
                    'unit' => 'unit',
                ],
                [
                    'entity_id' => $ingredient->id,
                    'entity_type' => 'ingredient',
                    'quantity' => 2,
                    'unit' => 'unit',
                ],
            ],
        ];

        $this->actingAs($user)
            ->postJson('/api/menus', $payload)
            ->assertStatus(422);
    }
}
