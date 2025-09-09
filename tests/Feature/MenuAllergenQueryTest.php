<?php

namespace Tests\Feature;

use App\Enums\Allergen;
use App\Enums\MeasurementUnit;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class MenuAllergenQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_it_filters_menus_by_allergen(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->for($user->company)->create();

        $milk = Ingredient::factory()->for($user->company)->create([
            'allergens' => [Allergen::MILK->value],
        ]);

        $gluten = Ingredient::factory()->for($user->company)->create([
            'allergens' => [Allergen::GLUTEN->value],
        ]);

        $menuWithMilk = Menu::factory()->for($user->company)->create();
        MenuItem::create([
            'menu_id' => $menuWithMilk->id,
            'entity_id' => $milk->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => MeasurementUnit::UNIT->value,
        ]);

        $menuWithGluten = Menu::factory()->for($user->company)->create();
        MenuItem::create([
            'menu_id' => $menuWithGluten->id,
            'entity_id' => $gluten->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => MeasurementUnit::UNIT->value,
        ]);

        $query = /* @lang GraphQL */ '
            query ($allergens: [AllergenEnum!]) {
                menus(allergens: $allergens) {
                    id
                    allergens
                }
            }
        ';

        $response = $this->actingAs($user)->graphQL($query, [
            'allergens' => [Allergen::MILK->value],
        ]);

        $response->assertJson([
            'data' => [
                'menus' => [
                    [
                        'id' => (string) $menuWithMilk->id,
                        'allergens' => [Allergen::MILK->value],
                    ],
                ],
            ],
        ]);

        $response->assertJsonMissing([
            'id' => (string) $menuWithGluten->id,
        ]);
    }
}
