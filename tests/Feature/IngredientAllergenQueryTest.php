<?php

namespace Tests\Feature;

use App\Enums\Allergen;
use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class IngredientAllergenQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_it_filters_ingredients_by_allergen_and_returns_values(): void
    {
        $user = User::factory()->create();

        $milk = Ingredient::factory()->for($user->company)->create([
            'allergens' => [Allergen::MILK->value],
        ]);

        $gluten = Ingredient::factory()->for($user->company)->create([
            'allergens' => [Allergen::GLUTEN->value],
        ]);

        $query = /* @lang GraphQL */ '
            query ($allergens: [AllergenEnum!]) {
                ingredients(allergens: $allergens) {
                    data {
                        id
                        allergens
                    }
                }
            }
        ';

        $response = $this->actingAs($user)->graphQL($query, [
            'allergens' => [Allergen::MILK->value],
        ]);

        $response->assertJson([
            'data' => [
                'ingredients' => [
                    'data' => [
                        [
                            'id' => (string) $milk->id,
                            'allergens' => [Allergen::MILK->value],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertJsonMissing([
            'id' => (string) $gluten->id,
        ]);
    }
}
