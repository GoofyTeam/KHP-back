<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class MenuTypeQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_company_has_default_menu_types_and_graphql_route(): void
    {
        $user = User::factory()->create();

        $expected = [
            ['name' => 'Entrées', 'position' => 0],
            ['name' => 'Plats', 'position' => 1],
            ['name' => 'Desserts', 'position' => 2],
            ['name' => 'Accompagnements', 'position' => 3],
        ];

        foreach ($expected as $type) {
            $menuType = MenuType::where('company_id', $user->company_id)
                ->where('name', $type['name'])
                ->first();

            $this->assertNotNull($menuType);
            $this->assertSame($type['position'], $menuType->public_index);
        }

        $query = /* @lang GraphQL */ '
            query {
                menuTypes {
                    name
                    public_index
                }
            }
        ';

        $response = $this->actingAs($user)->graphQL($query);
        $response->assertGraphQLErrorFree();

        $response->assertJsonCount(4, 'data.menuTypes');

        foreach ($expected as $index => $type) {
            $response->assertJsonPath("data.menuTypes.$index.name", $type['name']);
            $response->assertJsonPath("data.menuTypes.$index.public_index", $type['position']);
        }
    }

    public function test_it_filters_menus_by_any_of_multiple_types(): void
    {
        $user = User::factory()->create();

        $entreeType = MenuType::factory()->create(['company_id' => $user->company_id, 'name' => 'entrée']);
        $platType = MenuType::factory()->create(['company_id' => $user->company_id, 'name' => 'plat']);
        $dessertType = MenuType::factory()->create(['company_id' => $user->company_id, 'name' => 'dessert']);
        $sideType = MenuType::factory()->create(['company_id' => $user->company_id, 'name' => 'side']);

        $menuEntree = Menu::factory()->for($user->company)->create([
            'menu_type_id' => $entreeType->id,
        ]);

        $menuPlat = Menu::factory()->for($user->company)->create([
            'menu_type_id' => $platType->id,
        ]);

        $menuDessert = Menu::factory()->for($user->company)->create([
            'menu_type_id' => $dessertType->id,
        ]);

        $menuSide = Menu::factory()->for($user->company)->create([
            'menu_type_id' => $sideType->id,
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
