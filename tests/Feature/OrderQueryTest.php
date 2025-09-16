<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderStepStatus;
use App\Enums\StepMenuStatus;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderStep;
use App\Models\Room;
use App\Models\StepMenu;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class OrderQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private function createTableForUser(User $user): Table
    {
        $room = Room::factory()->for($user->company)->create();

        return Table::factory()
            ->for($room, 'room')
            ->for($user->company)
            ->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createOrderForUser(User $user, array $attributes = []): Order
    {
        $attributes = array_merge([
            'table_id' => $this->createTableForUser($user)->id,
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'status' => OrderStatus::PENDING,
        ], $attributes);

        return Order::create($attributes);
    }

    public function test_it_lists_orders_for_company(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $otherUser = User::factory()->create();
        $otherOrder = $this->createOrderForUser($otherUser);

        $response = $this->actingAs($user)->graphQL(/** @lang GraphQL */ '{
            orders {
                data { id }
            }
        }');

        $response->assertJsonCount(1, 'data.orders.data');
        $response->assertJsonFragment(['id' => (string) $order->id]);
        $response->assertJsonMissing(['id' => (string) $otherOrder->id]);
    }

    public function test_it_filters_orders_by_status(): void
    {
        $user = User::factory()->create();
        $pending = $this->createOrderForUser($user, ['status' => OrderStatus::PENDING]);
        $ready = $this->createOrderForUser($user, ['status' => OrderStatus::READY]);

        $query = /** @lang GraphQL */ 'query ($statuses: [OrderStatusEnum!]) {
            orders(statuses: $statuses) {
                data { id status }
            }
        }';

        $response = $this->actingAs($user)->graphQL($query, [
            'statuses' => [OrderStatus::PENDING->value],
        ]);

        $response->assertJsonCount(1, 'data.orders.data');
        $response->assertJsonFragment([
            'id' => (string) $pending->id,
            'status' => OrderStatus::PENDING->value,
        ]);
        $response->assertJsonMissing(['id' => (string) $ready->id]);
    }

    public function test_it_filters_orders_by_start_date(): void
    {
        $user = User::factory()->create();
        $older = $this->createOrderForUser($user);
        $older->forceFill(['created_at' => now()->subDays(3)])->save();

        $newer = $this->createOrderForUser($user);

        $query = /** @lang GraphQL */ 'query ($start: DateTime) {
            orders(start_date: $start) {
                data { id }
            }
        }';

        $response = $this->actingAs($user)->graphQL($query, [
            'start' => now()->subDay()->format('Y-m-d H:i:s'),
        ]);

        $response->assertJsonCount(1, 'data.orders.data');
        $response->assertJsonFragment(['id' => (string) $newer->id]);
        $response->assertJsonMissing(['id' => (string) $older->id]);
    }

    public function test_it_orders_orders_by_created_at_desc(): void
    {
        $user = User::factory()->create();
        $older = $this->createOrderForUser($user);
        $older->forceFill(['created_at' => now()->subDay()])->save();

        $newer = $this->createOrderForUser($user);

        $response = $this->actingAs($user)->graphQL(/** @lang GraphQL */ '{
            orders(orderBy: [{column: CREATED_AT, order: DESC}]) {
                data { id }
            }
        }');

        $response->assertJsonPath('data.orders.data.0.id', (string) $newer->id);
        $response->assertJsonPath('data.orders.data.1.id', (string) $older->id);
    }

    public function test_it_fetches_order_by_id(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $query = /** @lang GraphQL */ 'query ($id: ID!) {
            order(id: $id) { id status }
        }';

        $response = $this->actingAs($user)->graphQL($query, ['id' => $order->id]);

        $response->assertJsonPath('data.order.id', (string) $order->id);
        $response->assertJsonPath('data.order.status', OrderStatus::PENDING->value);
    }

    public function test_it_returns_orders_stats(): void
    {
        $user = User::factory()->create();

        $payedOrder = $this->createOrderForUser($user, ['status' => OrderStatus::PAYED]);
        $step = OrderStep::create([
            'order_id' => $payedOrder->id,
            'position' => 1,
            'status' => OrderStepStatus::SERVED,
        ]);

        $menuA = Menu::factory()->for($user->company)->create(['price' => 12.5]);
        $menuB = Menu::factory()->for($user->company)->create(['price' => 5.25]);

        StepMenu::create([
            'order_step_id' => $step->id,
            'menu_id' => $menuA->id,
            'quantity' => 2,
            'status' => StepMenuStatus::SERVED,
        ]);

        StepMenu::create([
            'order_step_id' => $step->id,
            'menu_id' => $menuB->id,
            'quantity' => 1,
            'status' => StepMenuStatus::SERVED,
        ]);

        $this->createOrderForUser($user, ['status' => OrderStatus::PENDING]);
        $this->createOrderForUser($user, ['status' => OrderStatus::CANCELLED]);

        $response = $this->actingAs($user)->graphQL(/** @lang GraphQL */ '{
            ordersStats {
                pending
                in_prep
                ready
                served
                payed
                cancelled
                total
                revenue
            }
        }');

        $response->assertJsonPath('data.ordersStats.pending', 1);
        $response->assertJsonPath('data.ordersStats.payed', 1);
        $response->assertJsonPath('data.ordersStats.cancelled', 1);
        $response->assertJsonPath('data.ordersStats.total', 3);
        $response->assertJsonPath('data.ordersStats.in_prep', 0);

        $expectedRevenue = (12.5 * 2) + 5.25;
        $this->assertEqualsWithDelta($expectedRevenue, $response->json('data.ordersStats.revenue'), 0.001);
    }

    public function test_order_prices_are_rounded_like_restaurants(): void
    {
        $user = User::factory()->create();

        $order = $this->createOrderForUser($user, ['status' => OrderStatus::PAYED]);
        $step = OrderStep::create([
            'order_id' => $order->id,
            'position' => 1,
            'status' => OrderStepStatus::SERVED,
        ]);

        $menu = Menu::factory()->for($user->company)->create(['price' => 10.1]);

        StepMenu::create([
            'order_step_id' => $step->id,
            'menu_id' => $menu->id,
            'quantity' => 3,
            'status' => StepMenuStatus::SERVED,
        ]);

        $response = $this->actingAs($user)->graphQL(/** @lang GraphQL */ '{
            orders {
                data { price }
            }
        }');

        $this->assertSame(30.3, $response->json('data.orders.data.0.price'));
    }

    public function test_orders_stats_revenue_is_rounded_like_restaurants(): void
    {
        $user = User::factory()->create();

        $order = $this->createOrderForUser($user, ['status' => OrderStatus::PAYED]);
        $step = OrderStep::create([
            'order_id' => $order->id,
            'position' => 1,
            'status' => OrderStepStatus::SERVED,
        ]);

        $menu = Menu::factory()->for($user->company)->create(['price' => 10.1]);

        StepMenu::create([
            'order_step_id' => $step->id,
            'menu_id' => $menu->id,
            'quantity' => 3,
            'status' => StepMenuStatus::SERVED,
        ]);

        $response = $this->actingAs($user)->graphQL(/** @lang GraphQL */ '{
            ordersStats {
                revenue
            }
        }');

        $this->assertSame(30.3, $response->json('data.ordersStats.revenue'));
    }
}
