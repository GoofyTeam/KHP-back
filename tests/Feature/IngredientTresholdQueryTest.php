<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class IngredientTresholdQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_it_lists_ingredients_below_their_threshold(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->for($user->company)->create();

        $below = Ingredient::factory()
            ->for($user->company)
            ->create(['threshold' => 10]);
        $below->locations()->attach($location->id, ['quantity' => 4]);

        $above = Ingredient::factory()
            ->for($user->company)
            ->create(['threshold' => 5]);
        $above->locations()->attach($location->id, ['quantity' => 7]);

        $withoutThreshold = Ingredient::factory()
            ->for($user->company)
            ->create(['threshold' => null]);
        $withoutThreshold->locations()->attach($location->id, ['quantity' => 1]);

        $query = /** @lang GraphQL */ '{
            ingredientTreshold {
                id
            }
        }';

        $response = $this->actingAs($user)->graphQL($query);

        $response->assertJsonCount(1, 'data.ingredientTreshold');
        $response->assertJsonFragment(['id' => (string) $below->id]);
        $response->assertJsonMissing(['id' => (string) $above->id]);
        $response->assertJsonMissing(['id' => (string) $withoutThreshold->id]);
    }

    public function test_it_can_filter_by_location_when_checking_threshold(): void
    {
        $user = User::factory()->create();
        $locationA = Location::factory()->for($user->company)->create();
        $locationB = Location::factory()->for($user->company)->create();

        $ingredient = Ingredient::factory()
            ->for($user->company)
            ->create(['threshold' => 10]);
        $ingredient->locations()->attach($locationA->id, ['quantity' => 4]);
        $ingredient->locations()->attach($locationB->id, ['quantity' => 12]);

        $query = /** @lang GraphQL */ '
            query ($locationIds: [ID!]) {
                ingredientTreshold(locationIds: $locationIds) {
                    id
                }
            }
        ';

        $responseForA = $this->actingAs($user)->graphQL($query, [
            'locationIds' => [$locationA->id],
        ]);
        $responseForA->assertJsonFragment(['id' => (string) $ingredient->id]);

        $responseForB = $this->actingAs($user)->graphQL($query, [
            'locationIds' => [$locationB->id],
        ]);
        $responseForB->assertJsonMissing(['id' => (string) $ingredient->id]);
    }
}
