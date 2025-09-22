<?php

namespace Tests\Feature;

use App\Enums\MenuServiceType;
use App\Enums\OrderHistoryAction;
use App\Enums\OrderStatus;
use App\Enums\OrderStepStatus;
use App\Enums\StepMenuStatus;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderStep;
use App\Models\Room;
use App\Models\StepMenu;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class OrderHistoryTest extends TestCase
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

    private function createMenuForUser(User $user, array $attributes = []): Menu
    {
        return Menu::factory()->create(array_merge([
            'company_id' => $user->company_id,
            'service_type' => MenuServiceType::PREP,
            'price' => 12.5,
        ], $attributes));
    }

    public function test_it_records_order_creation_and_step_creation(): void
    {
        $user = User::factory()->create();
        $table = $this->createTableForUser($user);
        $menu = $this->createMenuForUser($user);

        $orderResponse = $this->actingAs($user)->postJson('/api/orders', [
            'table_id' => $table->id,
        ]);

        $orderResponse->assertCreated();

        $orderId = (int) $orderResponse->json('order.id');
        $order = Order::findOrFail($orderId);

        $historyAfterCreation = OrderHistory::where('order_id', $orderId)->get();
        self::assertTrue(
            $historyAfterCreation->contains(fn (OrderHistory $entry) => $entry->action === OrderHistoryAction::ORDER_CREATED->value)
        );

        $this->actingAs($user)->postJson("/api/orders/{$orderId}/steps", [
            'menus' => [
                [
                    'menu_id' => $menu->id,
                    'quantity' => 2,
                ],
            ],
        ])->assertCreated();

        $histories = OrderHistory::where('order_id', $orderId)
            ->orderBy('created_at')
            ->get();

        self::assertTrue(
            $histories->contains(fn (OrderHistory $entry) => $entry->action === OrderHistoryAction::ORDER_STEP_CREATED->value)
        );
        $menuAddition = $histories->firstWhere('action', OrderHistoryAction::STEP_MENU_ADDED->value);
        self::assertNotNull($menuAddition);
        self::assertSame(2, $menuAddition->payload['quantity']);
        self::assertSame($menu->id, $menuAddition->payload['menu_id']);
        self::assertSame($user->id, $menuAddition->user_id);
    }

    public function test_it_records_step_menu_status_transitions(): void
    {
        $user = User::factory()->create();
        $table = $this->createTableForUser($user);
        $menu = $this->createMenuForUser($user);

        $orderId = (int) $this->actingAs($user)->postJson('/api/orders', [
            'table_id' => $table->id,
        ])->assertCreated()->json('order.id');

        $this->actingAs($user)->postJson("/api/orders/{$orderId}/steps", [
            'menus' => [
                [
                    'menu_id' => $menu->id,
                    'quantity' => 1,
                ],
            ],
        ])->assertCreated();

        $order = Order::with('steps.stepMenus')->findOrFail($orderId);
        /** @var OrderStep $step */
        $step = $order->steps->first();
        /** @var StepMenu $stepMenu */
        $stepMenu = $step->stepMenus->first();

        $this->actingAs($user)
            ->postJson("/api/orders/{$orderId}/step-menus/{$stepMenu->id}/ready")
            ->assertOk();

        $readyMenuHistory = OrderHistory::where('order_id', $orderId)
            ->where('action', OrderHistoryAction::STEP_MENU_STATUS_UPDATED->value)
            ->get()
            ->last(fn (OrderHistory $entry) => ($entry->payload['to'] ?? null) === StepMenuStatus::READY->value);

        self::assertNotNull($readyMenuHistory);
        self::assertSame(StepMenuStatus::IN_PREP->value, $readyMenuHistory->payload['from']);

        $readyStepHistory = OrderHistory::where('order_id', $orderId)
            ->where('action', OrderHistoryAction::ORDER_STEP_STATUS_UPDATED->value)
            ->get()
            ->last(fn (OrderHistory $entry) => ($entry->payload['to'] ?? null) === OrderStepStatus::READY->value);

        self::assertNotNull($readyStepHistory);
        self::assertSame(OrderStepStatus::IN_PREP->value, $readyStepHistory->payload['from']);

        $orderStatusHistoriesAfterReady = OrderHistory::where('order_id', $orderId)
            ->where('action', OrderHistoryAction::ORDER_STATUS_UPDATED->value)
            ->get();

        self::assertTrue($orderStatusHistoriesAfterReady->isEmpty());

        $this->actingAs($user)
            ->postJson("/api/orders/{$orderId}/step-menus/{$stepMenu->id}/served")
            ->assertOk();

        $servedMenuHistory = OrderHistory::where('order_id', $orderId)
            ->where('action', OrderHistoryAction::STEP_MENU_STATUS_UPDATED->value)
            ->get()
            ->last(fn (OrderHistory $entry) => ($entry->payload['to'] ?? null) === StepMenuStatus::SERVED->value);

        self::assertNotNull($servedMenuHistory);
        self::assertSame(StepMenuStatus::READY->value, $servedMenuHistory->payload['from']);

        $servedStepHistory = OrderHistory::where('order_id', $orderId)
            ->where('action', OrderHistoryAction::ORDER_STEP_STATUS_UPDATED->value)
            ->get()
            ->last(fn (OrderHistory $entry) => ($entry->payload['to'] ?? null) === OrderStepStatus::SERVED->value);

        self::assertNotNull($servedStepHistory);
        self::assertSame(OrderStepStatus::READY->value, $servedStepHistory->payload['from']);

        $servedOrderHistory = OrderHistory::where('order_id', $orderId)
            ->where('action', OrderHistoryAction::ORDER_STATUS_UPDATED->value)
            ->get()
            ->last(fn (OrderHistory $entry) => ($entry->payload['to'] ?? null) === OrderStatus::SERVED->value);

        self::assertNotNull($servedOrderHistory);
        self::assertSame(OrderStatus::PENDING->value, $servedOrderHistory->payload['from']);
    }

    public function test_it_records_step_menu_cancellations(): void
    {
        $user = User::factory()->create();
        $table = $this->createTableForUser($user);
        $menu = $this->createMenuForUser($user);

        $orderId = (int) $this->actingAs($user)->postJson('/api/orders', [
            'table_id' => $table->id,
        ])->assertCreated()->json('order.id');

        $this->actingAs($user)->postJson("/api/orders/{$orderId}/steps", [
            'menus' => [
                [
                    'menu_id' => $menu->id,
                    'quantity' => 3,
                ],
            ],
        ])->assertCreated();

        $order = Order::with('steps.stepMenus')->findOrFail($orderId);
        /** @var StepMenu $stepMenu */
        $stepMenu = $order->steps->first()->stepMenus->first();

        $this->actingAs($user)
            ->postJson("/api/orders/{$orderId}/step-menus/{$stepMenu->id}/cancel", [
                'quantity' => 1,
            ])
            ->assertOk();

        $updateHistory = OrderHistory::where('order_id', $orderId)
            ->where('action', OrderHistoryAction::STEP_MENU_UPDATED->value)
            ->latest()
            ->first();

        self::assertNotNull($updateHistory);
        self::assertSame(3, $updateHistory->payload['quantity_before']);
        self::assertSame(2, $updateHistory->payload['quantity_after']);
        self::assertSame(1, $updateHistory->payload['canceled_quantity']);
        self::assertSame('simple', $updateHistory->payload['type']);
        self::assertNull($updateHistory->reason);

        $this->actingAs($user)
            ->postJson("/api/orders/{$orderId}/step-menus/{$stepMenu->id}/cancel", [
                'quantity' => 2,
            ])
            ->assertOk();

        $removalHistory = OrderHistory::where('order_id', $orderId)
            ->where('action', OrderHistoryAction::STEP_MENU_REMOVED->value)
            ->latest()
            ->first();

        self::assertNotNull($removalHistory);
        self::assertSame(2, $removalHistory->payload['quantity_before']);
        self::assertSame(0, $removalHistory->payload['quantity_after']);
        self::assertSame(2, $removalHistory->payload['canceled_quantity']);
        self::assertSame('simple', $removalHistory->payload['type']);
        self::assertNull($removalHistory->reason);
    }

    public function test_it_records_returns_and_order_cancellation(): void
    {
        $user = User::factory()->create();
        $table = $this->createTableForUser($user);
        $menu = $this->createMenuForUser($user, [
            'service_type' => MenuServiceType::DIRECT,
            'is_returnable' => true,
        ]);

        $orderId = (int) $this->actingAs($user)->postJson('/api/orders', [
            'table_id' => $table->id,
        ])->assertCreated()->json('order.id');

        $this->actingAs($user)->postJson("/api/orders/{$orderId}/steps", [
            'menus' => [
                [
                    'menu_id' => $menu->id,
                    'quantity' => 1,
                ],
            ],
        ])->assertCreated();

        $order = Order::with('steps.stepMenus')->findOrFail($orderId);
        /** @var StepMenu $stepMenu */
        $stepMenu = $order->steps->first()->stepMenus->first();

        $this->actingAs($user)
            ->postJson("/api/orders/{$orderId}/step-menus/{$stepMenu->id}/served")
            ->assertOk();

        $this->actingAs($user)
            ->postJson("/api/orders/{$orderId}/cancel", [
                'unopened_returns' => [$stepMenu->id],
            ])
            ->assertOk();

        $returnHistory = OrderHistory::where('order_id', $orderId)
            ->where('action', OrderHistoryAction::STEP_MENU_REMOVED->value)
            ->latest()
            ->first();

        self::assertNotNull($returnHistory);
        self::assertSame('return', $returnHistory->payload['type']);
        self::assertTrue($returnHistory->payload['return_accepted']);
        self::assertSame('RETURN_ACCEPTED', $returnHistory->reason);

        $orderStatusHistory = OrderHistory::where('order_id', $orderId)
            ->where('action', OrderHistoryAction::ORDER_STATUS_UPDATED->value)
            ->get()
            ->last(fn (OrderHistory $entry) => ($entry->payload['to'] ?? null) === OrderStatus::CANCELED->value);

        self::assertNotNull($orderStatusHistory);
        self::assertSame(OrderStatus::SERVED->value, $orderStatusHistory->payload['from']);
        self::assertSame(OrderStatus::CANCELED->value, $orderStatusHistory->payload['to']);
        self::assertSame('ORDER_CANCELED', $orderStatusHistory->reason);
        self::assertSame([$stepMenu->id], $orderStatusHistory->payload['return_step_menu_ids']);
    }

    public function test_it_records_order_payment(): void
    {
        $user = User::factory()->create();
        $table = $this->createTableForUser($user);
        $menu = $this->createMenuForUser($user);

        $orderId = (int) $this->actingAs($user)->postJson('/api/orders', [
            'table_id' => $table->id,
        ])->assertCreated()->json('order.id');

        $this->actingAs($user)->postJson("/api/orders/{$orderId}/steps", [
            'menus' => [
                [
                    'menu_id' => $menu->id,
                    'quantity' => 1,
                ],
            ],
        ])->assertCreated();

        $order = Order::with('steps.stepMenus')->findOrFail($orderId);
        /** @var StepMenu $stepMenu */
        $stepMenu = $order->steps->first()->stepMenus->first();

        $this->actingAs($user)
            ->postJson("/api/orders/{$orderId}/step-menus/{$stepMenu->id}/ready")
            ->assertOk();

        $this->actingAs($user)
            ->postJson("/api/orders/{$orderId}/step-menus/{$stepMenu->id}/served")
            ->assertOk();

        $this->actingAs($user)
            ->postJson("/api/orders/{$orderId}/pay")
            ->assertOk();

        $history = OrderHistory::where('order_id', $orderId)
            ->where('action', OrderHistoryAction::ORDER_STATUS_UPDATED->value)
            ->get()
            ->last(fn (OrderHistory $entry) => ($entry->payload['to'] ?? null) === OrderStatus::PAYED->value);

        self::assertNotNull($history);
        self::assertSame(OrderStatus::SERVED->value, $history->payload['from']);
        self::assertSame(OrderStatus::PAYED->value, $history->payload['to']);
        self::assertFalse($history->payload['force']);
        self::assertNull($history->reason);
    }

    public function test_order_history_is_available_through_graphql(): void
    {
        $user = User::factory()->create();
        $table = $this->createTableForUser($user);

        $order = Order::create([
            'table_id' => $table->id,
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'status' => OrderStatus::PENDING,
            'pending_at' => now(),
        ]);

        OrderHistory::factory()->for($order)
            ->create([
                'company_id' => $order->company_id,
                'user_id' => $user->id,
                'action' => OrderHistoryAction::ORDER_CREATED->value,
                'payload' => ['status' => OrderStatus::PENDING->value],
                'created_at' => now()->subMinutes(2),
            ]);

        OrderHistory::factory()->for($order)
            ->create([
                'company_id' => $order->company_id,
                'user_id' => $user->id,
                'action' => OrderHistoryAction::ORDER_STATUS_UPDATED->value,
                'payload' => [
                    'from' => OrderStatus::PENDING->value,
                    'to' => OrderStatus::SERVED->value,
                ],
                'reason' => 'ORDER_SERVED',
                'created_at' => now()->subMinute(),
            ]);

        $query = /** @lang GraphQL */ 'query ($id: ID!) {
            order(id: $id) {
                id
                histories {
                    action
                    reason
                    payload
                }
            }
        }';

        $response = $this->actingAs($user)->graphQL($query, ['id' => $order->id]);

        $response->assertJsonPath('data.order.histories.0.action', OrderHistoryAction::ORDER_CREATED->name);
        $response->assertJsonPath('data.order.histories.1.action', OrderHistoryAction::ORDER_STATUS_UPDATED->name);
        $response->assertJsonPath('data.order.histories.0.payload.status', OrderStatus::PENDING->value);
        $response->assertJsonPath('data.order.histories.0.reason', null);
        $response->assertJsonPath('data.order.histories.1.payload.to', OrderStatus::SERVED->value);
        $response->assertJsonPath('data.order.histories.1.reason', 'ORDER_SERVED');
    }
}
