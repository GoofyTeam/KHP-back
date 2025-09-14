<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Menu;
use App\Models\MenuOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class MenuOrderQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_it_filters_menu_orders_by_status(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $menu = Menu::factory()->create(['company_id' => $company->id]);

        $pending = MenuOrder::factory()->create([
            'menu_id' => $menu->id,
            'status' => 'pending',
            'quantity' => 1,
        ]);

        MenuOrder::factory()->create([
            'menu_id' => $menu->id,
            'status' => 'completed',
            'quantity' => 1,
        ]);

        $query = /* @lang GraphQL */ '
            query ($status: String) {
                menuOrders(status: $status) {
                    data {
                        id
                        status
                    }
                }
            }
        ';

        $this->actingAs($user)->graphQL($query, ['status' => 'pending'])
            ->assertJson([
                'data' => [
                    'menuOrders' => [
                        'data' => [
                            ['id' => (string) $pending->id, 'status' => 'pending'],
                        ],
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data.menuOrders.data');
    }

    public function test_it_orders_menu_orders_by_status_and_creation_date(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $menu = Menu::factory()->create(['company_id' => $company->id]);

        $order1 = MenuOrder::factory()->create([
            'menu_id' => $menu->id,
            'status' => 'pending',
            'quantity' => 1,
            'created_at' => Carbon::parse('2024-01-01'),
            'updated_at' => Carbon::parse('2024-01-01'),
        ]);

        $order2 = MenuOrder::factory()->create([
            'menu_id' => $menu->id,
            'status' => 'completed',
            'quantity' => 1,
            'created_at' => Carbon::parse('2024-01-02'),
            'updated_at' => Carbon::parse('2024-01-02'),
        ]);

        $order3 = MenuOrder::factory()->create([
            'menu_id' => $menu->id,
            'status' => 'pending',
            'quantity' => 1,
            'created_at' => Carbon::parse('2024-01-03'),
            'updated_at' => Carbon::parse('2024-01-03'),
        ]);

        $response = $this->actingAs($user)->graphQL(/** @lang GraphQL */ '
            {
                menuOrders(orderBy: [{column: STATUS, order: ASC}, {column: CREATED_AT, order: DESC}]) {
                    data { id }
                }
            }
        ');

        $response->assertJson([
            'data' => [
                'menuOrders' => [
                    'data' => [
                        ['id' => (string) $order2->id],
                        ['id' => (string) $order3->id],
                        ['id' => (string) $order1->id],
                    ],
                ],
            ],
        ]);
    }
}
