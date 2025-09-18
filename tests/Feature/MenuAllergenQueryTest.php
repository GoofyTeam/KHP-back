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

    public function test_it_filters_menus_by_any_of_multiple_allergens(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->for($user->company)->create();

        $milk = Ingredient::factory()->for($user->company)->create([
            'allergens' => [Allergen::MILK->value],
        ]);

        $gluten = Ingredient::factory()->for($user->company)->create([
            'allergens' => [Allergen::GLUTEN->value],
        ]);

        $eggs = Ingredient::factory()->for($user->company)->create([
            'allergens' => [Allergen::EGGS->value],
        ]);

        $menuMilk = Menu::factory()->for($user->company)->create();
        MenuItem::create([
            'menu_id' => $menuMilk->id,
            'entity_id' => $milk->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => MeasurementUnit::UNIT->value,
        ]);

        $menuGluten = Menu::factory()->for($user->company)->create();
        MenuItem::create([
            'menu_id' => $menuGluten->id,
            'entity_id' => $gluten->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => MeasurementUnit::UNIT->value,
        ]);

        $menuBoth = Menu::factory()->for($user->company)->create();
        MenuItem::create([
            'menu_id' => $menuBoth->id,
            'entity_id' => $milk->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => MeasurementUnit::UNIT->value,
        ]);
        MenuItem::create([
            'menu_id' => $menuBoth->id,
            'entity_id' => $gluten->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => MeasurementUnit::UNIT->value,
        ]);

        $menuEgg = Menu::factory()->for($user->company)->create();
        MenuItem::create([
            'menu_id' => $menuEgg->id,
            'entity_id' => $eggs->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => MeasurementUnit::UNIT->value,
        ]);

        $query = /* @lang GraphQL */ '
            query ($allergens: [AllergenEnum!]) {
                menus(allergens: $allergens) {
                    data {
                        id
                    }
                }
            }
        ';

        $response = $this->actingAs($user)->graphQL($query, [
            'allergens' => [Allergen::MILK->value, Allergen::GLUTEN->value],
        ]);

        $response->assertJsonCount(3, 'data.menus.data');
        $response->assertJsonFragment(['id' => (string) $menuMilk->id]);
        $response->assertJsonFragment(['id' => (string) $menuGluten->id]);
        $response->assertJsonFragment(['id' => (string) $menuBoth->id]);
        $response->assertJsonMissing(['id' => (string) $menuEgg->id]);
    }
}
