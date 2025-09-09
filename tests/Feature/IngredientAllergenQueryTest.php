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

    public function test_it_filters_ingredients_by_any_of_multiple_allergens(): void
    {
        $user = User::factory()->create();

        $milk = Ingredient::factory()->for($user->company)->create([
            'allergens' => [Allergen::MILK->value],
        ]);

        $gluten = Ingredient::factory()->for($user->company)->create([
            'allergens' => [Allergen::GLUTEN->value],
        ]);

        $both = Ingredient::factory()->for($user->company)->create([
            'allergens' => [Allergen::MILK->value, Allergen::GLUTEN->value],
        ]);

        $eggs = Ingredient::factory()->for($user->company)->create([
            'allergens' => [Allergen::EGGS->value],
        ]);

        $query = /* @lang GraphQL */ '
            query ($allergens: [AllergenEnum!]) {
                ingredients(allergens: $allergens) {
                    data {
                        id
                    }
                }
            }
        ';

        $response = $this->actingAs($user)->graphQL($query, [
            'allergens' => [Allergen::MILK->value, Allergen::GLUTEN->value],
        ]);

        $response->assertJsonCount(3, 'data.ingredients.data');
        $response->assertJsonFragment(['id' => (string) $milk->id]);
        $response->assertJsonFragment(['id' => (string) $gluten->id]);
        $response->assertJsonFragment(['id' => (string) $both->id]);
        $response->assertJsonMissing(['id' => (string) $eggs->id]);
    }
}
