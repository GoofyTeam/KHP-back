<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class MenuTypeQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_it_filters_menus_by_any_of_multiple_types(): void
    {
        $user = User::factory()->create();

        $menuEntree = Menu::factory()->for($user->company)->create([
            'type' => 'entrée',
        ]);

        $menuPlat = Menu::factory()->for($user->company)->create([
            'type' => 'plat',
        ]);

        $menuDessert = Menu::factory()->for($user->company)->create([
            'type' => 'dessert',
        ]);

        $menuSide = Menu::factory()->for($user->company)->create([
            'type' => 'side',
        ]);

        $query = /* @lang GraphQL */ '
            query ($types: [String!]) {
                menus(types: $types) {
                    data {
                        id
                    }
                }
            }
        ';

        $response = $this->actingAs($user)->graphQL($query, [
            'types' => ['plat', 'dessert'],
        ]);

        $response->assertJsonCount(2, 'data.menus.data');
        $response->assertJsonFragment(['id' => (string) $menuPlat->id]);
        $response->assertJsonFragment(['id' => (string) $menuDessert->id]);
        $response->assertJsonMissing(['id' => (string) $menuEntree->id]);
        $response->assertJsonMissing(['id' => (string) $menuSide->id]);
    }
}
