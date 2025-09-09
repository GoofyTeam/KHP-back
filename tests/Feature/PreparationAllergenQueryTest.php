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

    public function test_it_filters_preparations_by_any_of_multiple_allergens(): void
    {
        $user = User::factory()->create();

        $milk = Ingredient::factory()->for($user->company)->create([
            'allergens' => [Allergen::MILK->value],
        ]);

        $gluten = Ingredient::factory()->for($user->company)->create([
            'allergens' => [Allergen::GLUTEN->value],
        ]);

        $eggs = Ingredient::factory()->for($user->company)->create([
            'allergens' => [Allergen::EGGS->value],
        ]);

        $prepMilk = Preparation::factory()->for($user->company)->create();
        PreparationEntity::create([
            'preparation_id' => $prepMilk->id,
            'entity_id' => $milk->id,
            'entity_type' => Ingredient::class,
        ]);

        $prepGluten = Preparation::factory()->for($user->company)->create();
        PreparationEntity::create([
            'preparation_id' => $prepGluten->id,
            'entity_id' => $gluten->id,
            'entity_type' => Ingredient::class,
        ]);

        $prepBoth = Preparation::factory()->for($user->company)->create();
        PreparationEntity::create([
            'preparation_id' => $prepBoth->id,
            'entity_id' => $milk->id,
            'entity_type' => Ingredient::class,
        ]);
        PreparationEntity::create([
            'preparation_id' => $prepBoth->id,
            'entity_id' => $gluten->id,
            'entity_type' => Ingredient::class,
        ]);

        $prepEgg = Preparation::factory()->for($user->company)->create();
        PreparationEntity::create([
            'preparation_id' => $prepEgg->id,
            'entity_id' => $eggs->id,
            'entity_type' => Ingredient::class,
        ]);

        $query = /* @lang GraphQL */ '
            query ($allergens: [AllergenEnum!]) {
                preparations(allergens: $allergens) {
                    data {
                        id
                    }
                }
            }
        ';

        $response = $this->actingAs($user)->graphQL($query, [
            'allergens' => [Allergen::MILK->value, Allergen::GLUTEN->value],
        ]);

        $response->assertJsonCount(3, 'data.preparations.data');
        $response->assertJsonFragment(['id' => (string) $prepMilk->id]);
        $response->assertJsonFragment(['id' => (string) $prepGluten->id]);
        $response->assertJsonFragment(['id' => (string) $prepBoth->id]);
        $response->assertJsonMissing(['id' => (string) $prepEgg->id]);
    }
}
