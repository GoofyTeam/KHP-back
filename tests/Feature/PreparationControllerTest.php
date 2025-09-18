<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Preparation;
use App\Models\PreparationEntity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class PreparationControllerTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_store_creates_entities_with_quantities_and_locations(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id, 'unit' => 'kg', 'base_unit' => 'kg']);
        $location = Location::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Test prep',
            'unit' => 'kg',
            'base_quantity' => 1,
            'base_unit' => 'kg',
            'entities' => [
                [
                    'id' => $ingredient->id,
                    'type' => 'ingredient',
                    'quantity' => 1.5,
                    'unit' => 'kg',
                    'location_id' => $location->id,
                ],
            ],
            'category_id' => $category->id,
        ];

        $this->actingAs($user)
            ->postJson('/api/preparations', $payload)
            ->assertStatus(201);

        $this->assertDatabaseHas('preparation_entities', [
            'entity_id' => $ingredient->id,
            'location_id' => $location->id,
            'quantity' => 1.5,
        ]);
    }

    public function test_update_syncs_entities_from_single_payload(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);
        $ingredientA = Ingredient::factory()->create(['company_id' => $company->id, 'unit' => 'kg', 'base_unit' => 'kg']);
        $ingredientB = Ingredient::factory()->create(['company_id' => $company->id, 'unit' => 'kg', 'base_unit' => 'kg']);
        $ingredientC = Ingredient::factory()->create(['company_id' => $company->id, 'unit' => 'kg', 'base_unit' => 'kg']);
        $locationA = Location::factory()->create(['company_id' => $company->id]);
        $locationB = Location::factory()->create(['company_id' => $company->id]);
        $locationC = Location::factory()->create(['company_id' => $company->id]);

        $preparation = Preparation::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'unit' => 'kg',
            'base_quantity' => 1,
            'base_unit' => 'kg',
        ]);

        PreparationEntity::create([
            'preparation_id' => $preparation->id,
            'entity_id' => $ingredientA->id,
            'entity_type' => Ingredient::class,
            'location_id' => $locationA->id,
            'quantity' => 1,
            'unit' => 'kg',
        ]);

        PreparationEntity::create([
            'preparation_id' => $preparation->id,
            'entity_id' => $ingredientB->id,
            'entity_type' => Ingredient::class,
            'location_id' => $locationB->id,
            'quantity' => 2,
            'unit' => 'kg',
        ]);

        $payload = [
            'entities' => [
                [
                    'id' => $ingredientA->id,
                    'type' => 'ingredient',
                    'quantity' => 1.5,
                    'unit' => 'kg',
                    'location_id' => $locationC->id,
                ],
                [
                    'id' => $ingredientC->id,
                    'type' => 'ingredient',
                    'quantity' => 0.75,
                    'unit' => 'kg',
                    'location_id' => $locationA->id,
                ],
            ],
        ];

        $this->actingAs($user)
            ->putJson("/api/preparations/{$preparation->id}", $payload)
            ->assertStatus(200);

        $this->assertDatabaseHas('preparation_entities', [
            'preparation_id' => $preparation->id,
            'entity_id' => $ingredientA->id,
            'entity_type' => Ingredient::class,
            'location_id' => $locationC->id,
            'quantity' => 1.5,
        ]);

        $this->assertDatabaseHas('preparation_entities', [
            'preparation_id' => $preparation->id,
            'entity_id' => $ingredientC->id,
            'entity_type' => Ingredient::class,
            'location_id' => $locationA->id,
            'quantity' => 0.75,
        ]);

        $this->assertDatabaseMissing('preparation_entities', [
            'preparation_id' => $preparation->id,
            'entity_id' => $ingredientB->id,
            'entity_type' => Ingredient::class,
        ]);
    }

    public function test_update_overrides_existing_entity_configuration(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create([
            'company_id' => $company->id,
            'unit' => 'kg',
            'base_unit' => 'kg',
        ]);
        $originalLocation = Location::factory()->create(['company_id' => $company->id]);
        $newLocation = Location::factory()->create(['company_id' => $company->id]);

        $preparation = Preparation::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'unit' => 'kg',
            'base_quantity' => 1,
            'base_unit' => 'kg',
        ]);

        PreparationEntity::create([
            'preparation_id' => $preparation->id,
            'entity_id' => $ingredient->id,
            'entity_type' => Ingredient::class,
            'location_id' => $originalLocation->id,
            'quantity' => 1,
            'unit' => 'kg',
        ]);

        $payload = [
            'entities' => [
                [
                    'id' => $ingredient->id,
                    'type' => 'ingredient',
                    'quantity' => 2,
                    'unit' => 'kg',
                    'location_id' => $newLocation->id,
                ],
            ],
        ];

        $this->actingAs($user)
            ->putJson("/api/preparations/{$preparation->id}", $payload)
            ->assertStatus(200);

        $this->assertDatabaseHas('preparation_entities', [
            'preparation_id' => $preparation->id,
            'entity_id' => $ingredient->id,
            'entity_type' => Ingredient::class,
            'location_id' => $newLocation->id,
            'quantity' => 2,
        ]);

        $this->assertDatabaseMissing('preparation_entities', [
            'preparation_id' => $preparation->id,
            'entity_id' => $ingredient->id,
            'entity_type' => Ingredient::class,
            'location_id' => $originalLocation->id,
        ]);

        $this->assertSame(1, PreparationEntity::where('preparation_id', $preparation->id)->count());
    }

    public function test_prepare_consumes_components_and_adds_stock(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id, 'unit' => 'kg', 'base_unit' => 'kg']);
        $componentLocation = Location::factory()->create(['company_id' => $company->id]);
        $destLocation = Location::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);

        $prep = Preparation::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'unit' => 'kg',
            'base_quantity' => 1,
            'base_unit' => 'kg',
        ]);

        PreparationEntity::create([
            'preparation_id' => $prep->id,
            'entity_id' => $ingredient->id,
            'entity_type' => Ingredient::class,
            'location_id' => $componentLocation->id,
            'quantity' => 1,
            'unit' => 'kg',
        ]);

        $ingredient->locations()->syncWithoutDetaching([$componentLocation->id => ['quantity' => 5]]);

        $this->actingAs($user)
            ->postJson("/api/preparations/{$prep->id}/prepare", [
                'quantity' => 2,
                'location_id' => $destLocation->id,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ingredient->id,
            'location_id' => $componentLocation->id,
            'quantity' => 3.0,
        ]);
        $this->assertDatabaseHas('location_preparation', [
            'preparation_id' => $prep->id,
            'location_id' => $destLocation->id,
            'quantity' => 2.0,
        ]);
    }

    public function test_prepare_allows_overriding_component_location_and_quantity(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id, 'unit' => 'kg', 'base_unit' => 'kg']);
        $defaultLocation = Location::factory()->create(['company_id' => $company->id]);
        $alternateLocation = Location::factory()->create(['company_id' => $company->id]);
        $destLocation = Location::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);

        $prep = Preparation::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'unit' => 'kg',
            'base_quantity' => 1,
            'base_unit' => 'kg',
        ]);

        PreparationEntity::create([
            'preparation_id' => $prep->id,
            'entity_id' => $ingredient->id,
            'entity_type' => Ingredient::class,
            'location_id' => $defaultLocation->id,
            'quantity' => 1,
            'unit' => 'kg',
        ]);

        $ingredient->locations()->syncWithoutDetaching([
            $defaultLocation->id => ['quantity' => 0.5],
            $alternateLocation->id => ['quantity' => 3],
        ]);

        $this->actingAs($user)
            ->postJson("/api/preparations/{$prep->id}/prepare", [
                'quantity' => 2,
                'location_id' => $destLocation->id,
                'overrides' => [
                    [
                        'id' => $ingredient->id,
                        'type' => 'ingredient',
                        'location_id' => $alternateLocation->id,
                        'quantity' => 750,
                        'unit' => 'g',
                    ],
                ],
            ])
            ->assertStatus(200);

        $alternateRemaining = (float) DB::table('ingredient_location')
            ->where('ingredient_id', $ingredient->id)
            ->where('location_id', $alternateLocation->id)
            ->value('quantity');

        $defaultRemaining = (float) DB::table('ingredient_location')
            ->where('ingredient_id', $ingredient->id)
            ->where('location_id', $defaultLocation->id)
            ->value('quantity');

        $this->assertSame(1.5, round($alternateRemaining, 2));
        $this->assertSame(0.5, round($defaultRemaining, 2));

        $this->assertDatabaseHas('location_preparation', [
            'preparation_id' => $prep->id,
            'location_id' => $destLocation->id,
            'quantity' => 2.0,
        ]);
    }

    public function test_preparable_quantity_query_returns_minimum_possible(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id, 'unit' => 'kg', 'base_unit' => 'kg']);
        $location = Location::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);

        $prep = Preparation::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'unit' => 'kg',
            'base_quantity' => 1,
            'base_unit' => 'kg',
        ]);

        PreparationEntity::create([
            'preparation_id' => $prep->id,
            'entity_id' => $ingredient->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 0.5,
            'unit' => 'kg',
        ]);

        $ingredient->locations()->syncWithoutDetaching([$location->id => ['quantity' => 2]]);

        $response = $this->actingAs($user)->graphQL(/** @lang GraphQL */ '
            query($id: ID!) {
                preparation(id: $id) {
                    id
                    preparable_quantity {
                        quantity
                        unit
                    }
                }
            }
        ', ['id' => $prep->id]);

        $response->assertJsonStructure([
            'data' => [
                'preparation' => [
                    'preparable_quantity' => [
                        'quantity',
                        'unit',
                    ],
                ],
            ],
        ]);

        $payload = $response->json('data.preparation.preparable_quantity');

        $this->assertNotNull($payload);
        $this->assertEquals(4, (int) round($payload['quantity']));
        $this->assertSame('kg', $payload['unit']);
    }

    public function test_it_updates_preparation_threshold(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $preparation = Preparation::factory()->create([
            'company_id' => $company->id,
            'threshold' => null,
        ]);

        $this->actingAs($user)
            ->putJson("/api/preparations/{$preparation->id}/threshold", ['threshold' => 12.5])
            ->assertStatus(200)
            ->assertJson([
                'message' => 'Preparation threshold updated successfully',
                'threshold' => 12.5,
            ]);

        $this->assertDatabaseHas('preparations', [
            'id' => $preparation->id,
            'threshold' => 12.5,
        ]);

        $this->actingAs($user)
            ->putJson("/api/preparations/{$preparation->id}/threshold", ['threshold' => null])
            ->assertStatus(200)
            ->assertJson([
                'message' => 'Preparation threshold updated successfully',
                'threshold' => null,
            ]);

        $this->assertDatabaseHas('preparations', [
            'id' => $preparation->id,
            'threshold' => null,
        ]);
    }

    public function test_it_forbids_threshold_update_for_foreign_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $preparation = Preparation::factory()->create([
            'company_id' => $otherCompany->id,
            'threshold' => 5,
        ]);

        $this->actingAs($user)
            ->putJson("/api/preparations/{$preparation->id}/threshold", ['threshold' => 12])
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Unauthorized action',
            ]);

        $this->assertDatabaseHas('preparations', [
            'id' => $preparation->id,
            'threshold' => 5,
        ]);
    }

    public function test_it_resets_preparation_threshold(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $preparation = Preparation::factory()->create([
            'company_id' => $company->id,
            'threshold' => 8,
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/preparations/{$preparation->id}/threshold")
            ->assertStatus(200)
            ->assertJson([
                'message' => 'Preparation threshold reset successfully',
            ]);

        $this->assertDatabaseHas('preparations', [
            'id' => $preparation->id,
            'threshold' => null,
        ]);
    }

    public function test_it_forbids_threshold_reset_for_foreign_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $preparation = Preparation::factory()->create([
            'company_id' => $otherCompany->id,
            'threshold' => 7,
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/preparations/{$preparation->id}/threshold")
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Unauthorized action',
            ]);

        $this->assertDatabaseHas('preparations', [
            'id' => $preparation->id,
            'threshold' => 7,
        ]);
    }
}
