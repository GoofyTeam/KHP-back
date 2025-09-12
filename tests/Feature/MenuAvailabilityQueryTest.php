<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class MenuAvailabilityQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_menu_available_field_reflects_stock_for_requested_quantity(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user->company)->create();
        $location = Location::factory()->for($user->company)->create();
        $ingredient->locations()->sync([$location->id => ['quantity' => 5]]);

        $menu = Menu::factory()->for($user->company)->create();
        MenuItem::create([
            'menu_id' => $menu->id,
            'entity_id' => $ingredient->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 2,
            'unit' => 'unit',
        ]);

        $query = /** @lang GraphQL */ '
            query ($id: ID!, $quantity: Int!) {
                menu(id: $id) {
                    available(quantity: $quantity)
                }
            }
        ';

        $this->actingAs($user)
            ->graphQL($query, ['id' => $menu->id, 'quantity' => 2])
            ->assertJsonPath('data.menu.available', true);

        $this->actingAs($user)
            ->graphQL($query, ['id' => $menu->id, 'quantity' => 3])
            ->assertJsonPath('data.menu.available', false);
    }

    public function test_menus_query_filters_by_availability(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->for($user->company)->create();

        $ingredientAvailable = Ingredient::factory()->for($user->company)->create();
        $ingredientAvailable->locations()->sync([$location->id => ['quantity' => 5]]);
        $menuAvailable = Menu::factory()->for($user->company)->create();
        MenuItem::create([
            'menu_id' => $menuAvailable->id,
            'entity_id' => $ingredientAvailable->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => 'unit',
        ]);

        $ingredientUnavailable = Ingredient::factory()->for($user->company)->create();
        $ingredientUnavailable->locations()->sync([$location->id => ['quantity' => 0]]);
        $menuUnavailable = Menu::factory()->for($user->company)->create();
        MenuItem::create([
            'menu_id' => $menuUnavailable->id,
            'entity_id' => $ingredientUnavailable->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => 'unit',
        ]);

        $query = /** @lang GraphQL */ '
            query ($available: Boolean) {
                menus(available: $available) {
                    id
                }
            }
        ';

        $this->actingAs($user)
            ->graphQL($query, ['available' => true])
            ->assertJsonCount(1, 'data.menus')
            ->assertJsonFragment(['id' => (string) $menuAvailable->id])
            ->assertJsonMissing(['id' => (string) $menuUnavailable->id]);

        $this->actingAs($user)
            ->graphQL($query, ['available' => false])
            ->assertJsonCount(1, 'data.menus')
            ->assertJsonFragment(['id' => (string) $menuUnavailable->id])
            ->assertJsonMissing(['id' => (string) $menuAvailable->id]);
    }
}
