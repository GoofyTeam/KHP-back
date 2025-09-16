<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderStepStatus;
use App\Models\Order;
use App\Models\OrderStep;
use App\Models\Room;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class OrderStepQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createOrderForUser(User $user, array $attributes = []): Order
    {
        $room = Room::factory()->for($user->company)->create();
        $table = Table::factory()->for($room, 'room')->for($user->company)->create();

        $attributes = array_merge([
            'table_id' => $table->id,
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'status' => OrderStatus::PENDING,
        ], $attributes);

        return Order::create($attributes);
    }

    public function test_it_lists_order_steps_for_company(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);
        $step = OrderStep::create([
            'order_id' => $order->id,
            'position' => 1,
            'status' => OrderStepStatus::PENDING,
        ]);

        $otherUser = User::factory()->create();
        $otherOrder = $this->createOrderForUser($otherUser);
        OrderStep::create([
            'order_id' => $otherOrder->id,
            'position' => 1,
            'status' => OrderStepStatus::READY,
        ]);

        $query = /** @lang GraphQL */ 'query ($orderId: ID!) {
            orderSteps(order_id: $orderId) {
                data { id }
            }
        }';

        $response = $this->actingAs($user)->graphQL($query, ['orderId' => $order->id]);

        $response->assertJsonCount(1, 'data.orderSteps.data');
        $response->assertJsonFragment(['id' => (string) $step->id]);
    }

    public function test_it_filters_order_steps_by_status(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);
        $ready = OrderStep::create([
            'order_id' => $order->id,
            'position' => 1,
            'status' => OrderStepStatus::READY,
        ]);

        OrderStep::create([
            'order_id' => $order->id,
            'position' => 2,
            'status' => OrderStepStatus::PENDING,
        ]);

        $query = /** @lang GraphQL */ 'query ($statuses: [OrderStepStatusEnum!]) {
            orderSteps(statuses: $statuses) {
                data { id status }
            }
        }';

        $response = $this->actingAs($user)->graphQL($query, [
            'statuses' => [OrderStepStatus::READY->value],
        ]);

        $response->assertJsonCount(1, 'data.orderSteps.data');
        $response->assertJsonFragment([
            'id' => (string) $ready->id,
            'status' => OrderStepStatus::READY->value,
        ]);
    }

    public function test_it_orders_steps_by_position_desc(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);
        $first = OrderStep::create([
            'order_id' => $order->id,
            'position' => 1,
            'status' => OrderStepStatus::PENDING,
        ]);

        $second = OrderStep::create([
            'order_id' => $order->id,
            'position' => 2,
            'status' => OrderStepStatus::PENDING,
        ]);

        $response = $this->actingAs($user)->graphQL(/** @lang GraphQL */ '{
            orderSteps(orderBy: [{column: POSITION, order: DESC}]) {
                data { id }
            }
        }');

        $response->assertJsonPath('data.orderSteps.data.0.id', (string) $second->id);
        $response->assertJsonPath('data.orderSteps.data.1.id', (string) $first->id);
    }

    public function test_it_fetches_order_step_by_id(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);
        $step = OrderStep::create([
            'order_id' => $order->id,
            'position' => 1,
            'status' => OrderStepStatus::READY,
        ]);

        $query = /** @lang GraphQL */ 'query ($id: ID!) {
            orderStep(id: $id) { id status }
        }';

        $response = $this->actingAs($user)->graphQL($query, ['id' => $step->id]);

        $response->assertJsonPath('data.orderStep.id', (string) $step->id);
        $response->assertJsonPath('data.orderStep.status', OrderStepStatus::READY->value);
    }
}
