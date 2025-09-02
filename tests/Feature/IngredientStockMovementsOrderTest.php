<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class IngredientStockMovementsOrderTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_it_orders_stock_movements_by_created_at_desc(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        $first = $ingredient->recordStockMovement($location, 0, 5);
        $first->created_at = Carbon::now()->subDay();
        $first->save();

        $second = $ingredient->recordStockMovement($location, 5, 3);
        $second->created_at = Carbon::now();
        $second->save();

        $response = $this->graphQL(/** @lang GraphQL */ '
            query ($id: ID!) {
                ingredient(id: $id) {
                    stockMovements(orderBy: [{column: CREATED_AT, order: DESC}]) {
                        id
                    }
                }
            }
        ', ['id' => $ingredient->id]);

        $response->assertJson([
            'data' => [
                'ingredient' => [
                    'stockMovements' => [
                        ['id' => (string) $second->id],
                        ['id' => (string) $first->id],
                    ],
                ],
            ],
        ]);
    }
}
