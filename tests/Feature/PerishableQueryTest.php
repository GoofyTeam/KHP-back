<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class PerishableQueryTest extends TestCase
{
    use RefreshDatabase;
    use MakesGraphQLRequests;

    /** Scenario: GraphQL perishables query lists active perishable stock. */
    public function test_it_lists_active_perishables(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $locationType = LocationType::factory()->create();
        $location = Location::factory()->create([
            'company_id' => $company->id,
            'location_type_id' => $locationType->id,
        ]);
        $category = Category::factory()->create(['company_id' => $company->id]);
        $category->locationTypes()->attach($locationType->id, ['shelf_life_hours' => 24]);
        $ingredient = Ingredient::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
        ]);
        $ingredient->locations()->updateExistingPivot($location->id, ['quantity' => 0]);

        $this->actingAs($user)->postJson("/api/ingredients/{$ingredient->id}/adjust-quantity", [
            'location_id' => $location->id,
            'quantity' => 7.5,
        ])->assertStatus(200);

        $response = $this->actingAs($user)->graphQL(/** @lang GraphQL */ '
            query {
                perishables(filter: ACTIVE) {
                    ingredient { id }
                    quantity
                    expiration_at
                }
            }
        ');

        $response->assertJson([
            'data' => [
                'perishables' => [
                    [
                        'ingredient' => ['id' => (string) $ingredient->id],
                        'quantity' => 7.5,
                    ],
                ],
            ],
        ]);

        $this->assertNotNull(data_get($response->json(), 'data.perishables.0.expiration_at'));
    }
}

