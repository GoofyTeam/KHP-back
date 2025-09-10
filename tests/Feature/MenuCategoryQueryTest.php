<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class MenuCategoryQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_it_filters_menus_by_any_of_multiple_categories(): void
    {
        $user = User::factory()->create();

        $halal = MenuCategory::factory()->for($user->company)->create(['name' => 'halal']);
        $vegan = MenuCategory::factory()->for($user->company)->create(['name' => 'vegan']);
        $casher = MenuCategory::factory()->for($user->company)->create(['name' => 'casher']);

        $menuHalal = Menu::factory()->for($user->company)->create();
        $menuHalal->categories()->attach($halal);

        $menuVegan = Menu::factory()->for($user->company)->create();
        $menuVegan->categories()->attach($vegan);

        $menuBoth = Menu::factory()->for($user->company)->create();
        $menuBoth->categories()->attach([$halal->id, $vegan->id]);

        $menuCasher = Menu::factory()->for($user->company)->create();
        $menuCasher->categories()->attach($casher);

        $query = /* @lang GraphQL */ '
            query ($category_ids: [ID!]) {
                menus(category_ids: $category_ids) {
                    id
                }
            }
        ';

        $response = $this->actingAs($user)->graphQL($query, [
            'category_ids' => [$halal->id, $vegan->id],
        ]);

        $response->assertJsonCount(3, 'data.menus');
        $response->assertJsonFragment(['id' => (string) $menuHalal->id]);
        $response->assertJsonFragment(['id' => (string) $menuVegan->id]);
        $response->assertJsonFragment(['id' => (string) $menuBoth->id]);
        $response->assertJsonMissing(['id' => (string) $menuCasher->id]);
    }
}
