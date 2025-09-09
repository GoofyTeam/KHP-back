<?php

namespace Tests\Feature;

use App\Enums\Allergen;
use App\Models\Ingredient;
use App\Models\Preparation;
use App\Models\PreparationEntity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class PreparationAllergenQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_it_filters_preparations_by_allergen(): void
    {
        $user = User::factory()->create();

        $milk = Ingredient::factory()->for($user->company)->create([
            'allergens' => [Allergen::MILK->value],
        ]);

        $gluten = Ingredient::factory()->for($user->company)->create([
            'allergens' => [Allergen::GLUTEN->value],
        ]);

        $prepWithMilk = Preparation::factory()->for($user->company)->create();
        PreparationEntity::create([
            'preparation_id' => $prepWithMilk->id,
            'entity_id' => $milk->id,
            'entity_type' => Ingredient::class,
        ]);

        $prepWithGluten = Preparation::factory()->for($user->company)->create();
        PreparationEntity::create([
            'preparation_id' => $prepWithGluten->id,
            'entity_id' => $gluten->id,
            'entity_type' => Ingredient::class,
        ]);

        $query = /* @lang GraphQL */ '
            query ($allergens: [AllergenEnum!]) {
                preparations(allergens: $allergens) {
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
                'preparations' => [
                    'data' => [
                        [
                            'id' => (string) $prepWithMilk->id,
                            'allergens' => [Allergen::MILK->value],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertJsonMissing([
            'id' => (string) $prepWithGluten->id,
        ]);
    }
}
