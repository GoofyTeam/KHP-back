<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class MenuPriceQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_it_filters_menus_between_two_prices(): void
    {
        $user = User::factory()->create();

        $menuCheap = Menu::factory()->for($user->company)->create([
            'price' => 5,
        ]);

        $menuLow = Menu::factory()->for($user->company)->create([
            'price' => 10,
        ]);

        $menuMid = Menu::factory()->for($user->company)->create([
            'price' => 20,
        ]);

        $menuHigh = Menu::factory()->for($user->company)->create([
            'price' => 30,
        ]);

        $query = /* @lang GraphQL */ '
            query ($price_between: [Float!]) {
                menus(price_between: $price_between) {
                    id
                }
            }
        ';

        $response = $this->actingAs($user)->graphQL($query, [
            'price_between' => [10, 20],
        ]);

        $response->assertJsonCount(2, 'data.menus');
        $response->assertJsonFragment(['id' => (string) $menuLow->id]);
        $response->assertJsonFragment(['id' => (string) $menuMid->id]);
        $response->assertJsonMissing(['id' => (string) $menuCheap->id]);
        $response->assertJsonMissing(['id' => (string) $menuHigh->id]);
    }
}
