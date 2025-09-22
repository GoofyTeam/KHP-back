<?php

namespace Tests\Feature;

use App\Enums\MeasurementUnit;
use App\Enums\MenuServiceType;
use App\Enums\OrderStatus;
use App\Enums\OrderStepStatus;
use App\Enums\StepMenuStatus;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderStep;
use App\Models\Room;
use App\Models\StepMenu;
use App\Models\Table;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createTableForUser(User $user): Table
    {
        $room = Room::factory()->create(['company_id' => $user->company_id]);

        return Table::factory()
            ->for($room, 'room')
            ->for($user->company)
            ->create();
    }

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

    public function test_it_creates_simple_order(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-01 12:34:56'));

        $user = User::factory()->create();
        $table = $this->createTableForUser($user);

        try {
            $response = $this->actingAs($user)->postJson('/api/orders', [
                'table_id' => $table->id,
            ]);

            $response->assertCreated()
                ->assertJsonPath('message', 'Order created successfully.')
                ->assertJsonPath('order.table_id', $table->id)
                ->assertJsonPath('order.company_id', $user->company_id)
                ->assertJsonPath('order.user_id', $user->id)
                ->assertJsonPath('order.status', OrderStatus::PENDING->value)
                ->assertJsonPath('order.steps', []);

            $this->assertDatabaseHas('orders', [
                'table_id' => $table->id,
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'status' => OrderStatus::PENDING->value,
            ]);

            self::assertSame(
                Carbon::now()->toISOString(),
                Carbon::parse($response->json('order.pending_at'))->toISOString(),
            );

            self::assertSame(0.0, (float) $response->json('order.price'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_it_validates_table_belongs_to_company(): void
    {
        $user = User::factory()->create();
        $foreignTable = Table::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'table_id' => $foreignTable->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['table_id']);
    }

    public function test_it_creates_order_step_from_menus(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        OrderStep::create([
            'order_id' => $order->id,
            'position' => 1,
            'status' => OrderStepStatus::READY,
        ]);

        $menuA = Menu::factory()->create([
            'company_id' => $user->company_id,
            'price' => 12.5,
            'service_type' => MenuServiceType::PREP,
        ]);
        $menuB = Menu::factory()->create([
            'company_id' => $user->company_id,
            'price' => 7.0,
            'service_type' => MenuServiceType::DIRECT,
        ]);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/steps", [
            'menus' => [
                ['menu_id' => $menuA->id, 'quantity' => 2, 'note' => 'Sans sel'],
                ['menu_id' => $menuB->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('step.order_id', $order->id)
            ->assertJsonPath('step.position', 2)
            ->assertJsonCount(2, 'step.step_menus');

        $createdStepId = (int) $response->json('step.id');

        $this->assertDatabaseHas('order_steps', [
            'id' => $createdStepId,
            'order_id' => $order->id,
            'position' => 2,
            'status' => OrderStepStatus::IN_PREP->value,
        ]);

        $this->assertDatabaseHas('step_menus', [
            'order_step_id' => $createdStepId,
            'menu_id' => $menuA->id,
            'quantity' => 2,
            'status' => StepMenuStatus::IN_PREP->value,
            'note' => 'Sans sel',
        ]);

        $this->assertDatabaseHas('step_menus', [
            'order_step_id' => $createdStepId,
            'menu_id' => $menuB->id,
            'quantity' => 1,
            'status' => StepMenuStatus::READY->value,
            'note' => null,
        ]);

        $expectedPrice = (12.5 * 2) + 7.0;
        self::assertEqualsWithDelta($expectedPrice, $response->json('step.price'), 0.001);

        $this->assertSame(StepMenuStatus::IN_PREP->value, $response->json('step.step_menus.0.status'));
        $this->assertSame(StepMenuStatus::READY->value, $response->json('step.step_menus.1.status'));

        $order->refresh();
        self::assertNotNull($order->pending_at);
    }

    public function test_it_returns_404_when_order_not_in_company(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $order = $this->createOrderForUser($otherUser);
        $menu = Menu::factory()->create(['company_id' => $otherUser->company_id]);

        $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/steps", [
                'menus' => [
                    ['menu_id' => $menu->id, 'quantity' => 1],
                ],
            ])
            ->assertStatus(404);
    }

    public function test_it_validates_menu_company(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $foreignMenu = Menu::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/steps", [
            'menus' => [
                ['menu_id' => $foreignMenu->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['menus.0.menu_id']);
    }

    public function test_it_sets_pending_timestamp_when_creating_step(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user, ['pending_at' => null]);

        $this->actingAs($user)->postJson("/api/orders/{$order->id}/steps", [
            'menus' => [
                ['menu_id' => Menu::factory()->create(['company_id' => $user->company_id])->id, 'quantity' => 1],
            ],
        ])->assertStatus(201);

        $order->refresh();

        self::assertNotNull($order->pending_at);
    }

    public function test_it_adds_step_menu_to_step(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::SERVED,
            'served_at' => now(),
        ]);

        $existingMenu = Menu::factory()->create(['company_id' => $user->company_id]);

        StepMenu::factory()->for($step, 'step')->for($existingMenu)->create([
            'status' => StepMenuStatus::SERVED,
            'quantity' => 1,
            'served_at' => now(),
        ]);

        $newMenu = Menu::factory()->create([
            'company_id' => $user->company_id,
            'service_type' => MenuServiceType::PREP,
        ]);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/steps/{$step->id}/menus", [
            'menu_id' => $newMenu->id,
            'quantity' => 2,
            'note' => 'Extra spicy',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Menu added to step.')
            ->assertJsonPath('step.id', $step->id)
            ->assertJsonPath('step.status', OrderStepStatus::IN_PREP->value)
            ->assertJsonPath('step_menu.menu_id', $newMenu->id)
            ->assertJsonPath('step_menu.quantity', 2)
            ->assertJsonPath('step_menu.status', StepMenuStatus::IN_PREP->value)
            ->assertJsonPath('step_menu.note', 'Extra spicy');

        $step->refresh();

        self::assertNull($step->served_at);
        self::assertSame(OrderStepStatus::IN_PREP, $step->status);

        $this->assertDatabaseHas('step_menus', [
            'order_step_id' => $step->id,
            'menu_id' => $newMenu->id,
            'quantity' => 2,
            'status' => StepMenuStatus::IN_PREP->value,
            'note' => 'Extra spicy',
        ]);
    }

    public function test_it_keeps_step_ready_when_adding_direct_menu(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::READY,
        ]);

        $existingMenu = Menu::factory()->create(['company_id' => $user->company_id]);

        StepMenu::factory()->for($step, 'step')->for($existingMenu)->create([
            'status' => StepMenuStatus::READY,
            'quantity' => 1,
        ]);

        $directMenu = Menu::factory()->create([
            'company_id' => $user->company_id,
            'service_type' => MenuServiceType::DIRECT,
        ]);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/steps/{$step->id}/menus", [
            'menu_id' => $directMenu->id,
            'quantity' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('step.status', OrderStepStatus::READY->value)
            ->assertJsonPath('step_menu.status', StepMenuStatus::READY->value);

        $step->refresh();

        self::assertSame(OrderStepStatus::READY, $step->status);

        $this->assertDatabaseHas('step_menus', [
            'order_step_id' => $step->id,
            'menu_id' => $directMenu->id,
            'status' => StepMenuStatus::READY->value,
            'quantity' => 1,
        ]);
    }

    public function test_it_returns_404_when_adding_step_menu_outside_company(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $order = $this->createOrderForUser($otherUser);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::IN_PREP,
        ]);

        $menu = Menu::factory()->create(['company_id' => $otherUser->company_id]);

        $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/steps/{$step->id}/menus", [
                'menu_id' => $menu->id,
                'quantity' => 1,
            ])
            ->assertStatus(404);
    }

    public function test_it_returns_404_when_step_does_not_belong_to_order(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);
        $otherOrder = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($otherOrder)->create([
            'position' => 1,
            'status' => OrderStepStatus::IN_PREP,
        ]);

        $menu = Menu::factory()->create(['company_id' => $user->company_id]);

        $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/steps/{$step->id}/menus", [
                'menu_id' => $menu->id,
                'quantity' => 1,
            ])
            ->assertStatus(404);
    }

    public function test_it_requires_quantity_when_adding_step_menu(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::IN_PREP,
        ]);

        $menu = Menu::factory()->create(['company_id' => $user->company_id]);

        $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/steps/{$step->id}/menus", [
                'menu_id' => $menu->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_it_rejects_negative_quantity_when_adding_step_menu(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::IN_PREP,
        ]);

        $menu = Menu::factory()->create(['company_id' => $user->company_id]);

        $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/steps/{$step->id}/menus", [
                'menu_id' => $menu->id,
                'quantity' => -2,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_it_cancels_in_prep_step_menu_without_loss(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::IN_PREP,
        ]);

        $menu = Menu::factory()->create([
            'company_id' => $user->company_id,
            'service_type' => MenuServiceType::PREP,
        ]);

        $stepMenu = StepMenu::factory()->for($step, 'step')->for($menu)->create([
            'quantity' => 3,
            'status' => StepMenuStatus::IN_PREP,
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/orders/{$order->id}/step-menus/{$stepMenu->id}/cancel"
        );

        $response->assertOk()
            ->assertJsonPath('message', 'Step menu canceled successfully.')
            ->assertJsonPath('canceled_quantity', 3)
            ->assertJsonPath('loss_recorded', false)
            ->assertJsonPath('return_accepted', false);

        self::assertNull($response->json('step_menu'));

        $this->assertDatabaseMissing('step_menus', ['id' => $stepMenu->id]);
        $this->assertDatabaseCount('losses', 0);

        $step->refresh();
        self::assertSame(OrderStepStatus::IN_PREP, $step->status);
        self::assertNull($step->served_at);
    }

    public function test_it_records_kitchen_loss_when_canceling_ready_prep_menu(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $location = Location::factory()->create(['company_id' => $user->company_id]);
        $ingredient = Ingredient::factory()->create([
            'company_id' => $user->company_id,
            'unit' => MeasurementUnit::GRAM,
        ]);
        $ingredient->locations()->syncWithoutDetaching([$location->id => ['quantity' => 200]]);

        $menu = Menu::factory()->create([
            'company_id' => $user->company_id,
            'service_type' => MenuServiceType::PREP,
        ]);
        $menu->items()->create([
            'entity_id' => $ingredient->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 25,
            'unit' => MeasurementUnit::GRAM,
        ]);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::READY,
        ]);

        $stepMenu = StepMenu::factory()->for($step, 'step')->for($menu)->create([
            'quantity' => 2,
            'status' => StepMenuStatus::READY,
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/orders/{$order->id}/step-menus/{$stepMenu->id}/cancel",
            ['quantity' => 1]
        );

        $response->assertOk()
            ->assertJsonPath('loss_recorded', true)
            ->assertJsonPath('loss_reason', 'KITCHEN_LOSS')
            ->assertJsonPath('step_menu.quantity', 1)
            ->assertJsonPath('step_menu.status', StepMenuStatus::READY->value);

        $this->assertDatabaseHas('step_menus', [
            'id' => $stepMenu->id,
            'quantity' => 1,
        ]);

        $this->assertDatabaseHas('losses', [
            'loss_item_id' => $ingredient->id,
            'loss_item_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 25.0,
            'reason' => 'KITCHEN_LOSS',
        ]);

        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ingredient->id,
            'location_id' => $location->id,
            'quantity' => 175.0,
        ]);
    }

    public function test_it_accepts_unopened_return_for_served_direct_menu(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $location = Location::factory()->create(['company_id' => $user->company_id]);
        $ingredient = Ingredient::factory()->create([
            'company_id' => $user->company_id,
            'unit' => MeasurementUnit::UNIT,
        ]);
        $ingredient->locations()->syncWithoutDetaching([$location->id => ['quantity' => 10]]);

        $menu = Menu::factory()->create([
            'company_id' => $user->company_id,
            'service_type' => MenuServiceType::DIRECT,
            'is_returnable' => true,
        ]);
        $menu->items()->create([
            'entity_id' => $ingredient->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => MeasurementUnit::UNIT,
        ]);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::SERVED,
            'served_at' => now(),
        ]);

        $stepMenu = StepMenu::factory()->for($step, 'step')->for($menu)->create([
            'quantity' => 2,
            'status' => StepMenuStatus::SERVED,
            'served_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/orders/{$order->id}/step-menus/{$stepMenu->id}/cancel",
            ['unopened_return' => true]
        );

        $response->assertOk()
            ->assertJsonPath('return_accepted', true)
            ->assertJsonPath('loss_recorded', false);

        self::assertNull($response->json('step_menu'));

        $this->assertDatabaseMissing('step_menus', ['id' => $stepMenu->id]);
        $this->assertDatabaseCount('losses', 0);

        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ingredient->id,
            'location_id' => $location->id,
            'quantity' => 10.0,
        ]);
    }

    public function test_it_records_loss_for_served_direct_menu_without_unopened_return(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $location = Location::factory()->create(['company_id' => $user->company_id]);
        $ingredient = Ingredient::factory()->create([
            'company_id' => $user->company_id,
            'unit' => MeasurementUnit::UNIT,
        ]);
        $ingredient->locations()->syncWithoutDetaching([$location->id => ['quantity' => 6]]);

        $menu = Menu::factory()->create([
            'company_id' => $user->company_id,
            'service_type' => MenuServiceType::DIRECT,
            'is_returnable' => false,
        ]);
        $menu->items()->create([
            'entity_id' => $ingredient->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => MeasurementUnit::UNIT,
        ]);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::SERVED,
            'served_at' => now(),
        ]);

        $stepMenu = StepMenu::factory()->for($step, 'step')->for($menu)->create([
            'quantity' => 3,
            'status' => StepMenuStatus::SERVED,
            'served_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/orders/{$order->id}/step-menus/{$stepMenu->id}/cancel"
        );

        $response->assertOk()
            ->assertJsonPath('loss_recorded', true)
            ->assertJsonPath('loss_reason', 'SERVICE_LOSS');

        $this->assertDatabaseHas('losses', [
            'loss_item_id' => $ingredient->id,
            'loss_item_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 3.0,
            'reason' => 'SERVICE_LOSS',
        ]);

        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ingredient->id,
            'location_id' => $location->id,
            'quantity' => 3.0,
        ]);
    }

    public function test_it_rejects_cancelling_more_than_available(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::IN_PREP,
        ]);

        $menu = Menu::factory()->create([
            'company_id' => $user->company_id,
            'service_type' => MenuServiceType::PREP,
        ]);

        $stepMenu = StepMenu::factory()->for($step, 'step')->for($menu)->create([
            'quantity' => 1,
            'status' => StepMenuStatus::IN_PREP,
        ]);

        $this->actingAs($user)
            ->postJson(
                "/api/orders/{$order->id}/step-menus/{$stepMenu->id}/cancel",
                ['quantity' => 2]
            )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);

        $this->assertDatabaseHas('step_menus', ['id' => $stepMenu->id, 'quantity' => 1]);
    }

    public function test_it_marks_step_menu_ready_and_updates_step_when_all_ready(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::IN_PREP,
        ]);

        $readyMenu = Menu::factory()->create(['company_id' => $user->company_id]);
        $targetMenu = Menu::factory()->create(['company_id' => $user->company_id]);

        StepMenu::factory()->for($step, 'step')->for($readyMenu)->create([
            'status' => StepMenuStatus::READY,
            'quantity' => 1,
        ]);

        $stepMenu = StepMenu::factory()->for($step, 'step')->for($targetMenu)->create([
            'status' => StepMenuStatus::IN_PREP,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/step-menus/{$stepMenu->id}/ready");

        $response->assertOk()
            ->assertJsonPath('step_menu.id', $stepMenu->id)
            ->assertJsonPath('step_menu.status', StepMenuStatus::READY->value)
            ->assertJsonPath('step.status', OrderStepStatus::READY->value);

        $this->assertDatabaseHas('step_menus', [
            'id' => $stepMenu->id,
            'status' => StepMenuStatus::READY->value,
        ]);

        $this->assertDatabaseHas('order_steps', [
            'id' => $step->id,
            'status' => OrderStepStatus::READY->value,
        ]);
    }

    public function test_order_status_remains_pending_when_all_menus_ready(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::IN_PREP,
        ]);

        $readyMenu = Menu::factory()->create(['company_id' => $user->company_id]);
        StepMenu::factory()->for($step, 'step')->for($readyMenu)->create([
            'status' => StepMenuStatus::READY,
            'quantity' => 1,
        ]);

        $targetMenu = Menu::factory()->create(['company_id' => $user->company_id]);
        $stepMenu = StepMenu::factory()->for($step, 'step')->for($targetMenu)->create([
            'status' => StepMenuStatus::IN_PREP,
            'quantity' => 1,
        ]);

        $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/step-menus/{$stepMenu->id}/ready")
            ->assertOk();

        $order->refresh();

        self::assertSame(OrderStatus::PENDING, $order->status);
        self::assertNull($order->served_at);
    }

    public function test_order_status_becomes_served_when_all_menus_served(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::IN_PREP,
        ]);

        $servedMenu = Menu::factory()->create(['company_id' => $user->company_id]);
        Carbon::setTestNow(Carbon::parse('2024-01-01 12:00:00'));
        StepMenu::factory()->served()->for($step, 'step')->for($servedMenu)->create([
            'quantity' => 1,
        ]);

        $targetMenu = Menu::factory()->create(['company_id' => $user->company_id]);
        $stepMenu = StepMenu::factory()->for($step, 'step')->for($targetMenu)->create([
            'status' => StepMenuStatus::IN_PREP,
            'quantity' => 1,
        ]);

        $servedMoment = Carbon::parse('2024-01-01 12:10:00');
        Carbon::setTestNow($servedMoment);

        $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/step-menus/{$stepMenu->id}/ready")
            ->assertOk();

        $order->refresh();
        self::assertSame(OrderStatus::PENDING, $order->status);
        self::assertNull($order->served_at);

        $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/step-menus/{$stepMenu->id}/served")
            ->assertOk();

        try {
            $order->refresh();

            self::assertSame(OrderStatus::SERVED, $order->status);
            self::assertSame($servedMoment->toISOString(), optional($order->served_at)->toISOString());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_order_with_multiple_steps_switches_from_pending_to_served_once_all_menus_served(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $firstStep = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::SERVED,
            'served_at' => now(),
        ]);

        $firstStepMenuA = Menu::factory()->create(['company_id' => $user->company_id]);
        $firstStepMenuB = Menu::factory()->create(['company_id' => $user->company_id]);

        StepMenu::factory()->served()->for($firstStep, 'step')->for($firstStepMenuA)->create([
            'quantity' => 1,
        ]);
        StepMenu::factory()->served()->for($firstStep, 'step')->for($firstStepMenuB)->create([
            'quantity' => 2,
        ]);

        $secondStep = OrderStep::factory()->for($order)->create([
            'position' => 2,
            'status' => OrderStepStatus::IN_PREP,
        ]);

        $alreadyServedMenu = Menu::factory()->create(['company_id' => $user->company_id]);
        StepMenu::factory()->served()->for($secondStep, 'step')->for($alreadyServedMenu)->create([
            'quantity' => 1,
        ]);

        $targetMenu = Menu::factory()->create(['company_id' => $user->company_id]);
        $stepMenu = StepMenu::factory()->for($secondStep, 'step')->for($targetMenu)->create([
            'status' => StepMenuStatus::IN_PREP,
            'quantity' => 1,
        ]);

        $order->refresh();
        self::assertSame(OrderStatus::PENDING, $order->status);
        self::assertNull($order->served_at);

        $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/step-menus/{$stepMenu->id}/ready")
            ->assertOk();

        $secondStep->refresh();
        self::assertSame(OrderStepStatus::READY, $secondStep->status);

        $order->refresh();
        self::assertSame(OrderStatus::PENDING, $order->status);
        self::assertNull($order->served_at);

        $servedMoment = Carbon::parse('2024-03-15 18:45:00');
        Carbon::setTestNow($servedMoment);

        try {
            $this->actingAs($user)
                ->postJson("/api/orders/{$order->id}/step-menus/{$stepMenu->id}/served")
                ->assertOk();

            $secondStep->refresh();
            self::assertSame(OrderStepStatus::SERVED, $secondStep->status);

            $order->refresh();
            self::assertSame(OrderStatus::SERVED, $order->status);
            self::assertSame($servedMoment->toISOString(), optional($order->served_at)->toISOString());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_order_status_returns_to_pending_when_new_menu_added(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::IN_PREP,
        ]);

        $menuA = Menu::factory()->create([
            'company_id' => $user->company_id,
            'service_type' => MenuServiceType::PREP,
        ]);
        $menuB = Menu::factory()->create([
            'company_id' => $user->company_id,
            'service_type' => MenuServiceType::PREP,
        ]);
        $additionalMenu = Menu::factory()->create([
            'company_id' => $user->company_id,
            'service_type' => MenuServiceType::PREP,
        ]);

        $stepMenuA = StepMenu::factory()->served()->for($step, 'step')->for($menuA)->create([
            'quantity' => 1,
        ]);
        $stepMenuB = StepMenu::factory()->served()->for($step, 'step')->for($menuB)->create([
            'quantity' => 1,
        ]);

        $step->forceFill([
            'status' => OrderStepStatus::SERVED,
            'served_at' => now(),
        ])->save();
        $step->refresh();

        $order->refresh()->load('steps.stepMenus');
        $order->refreshStatusFromSteps();
        $order->refresh();
        self::assertSame(OrderStatus::SERVED, $order->status);
        self::assertNotNull($order->served_at);

        $revertMoment = Carbon::parse('2024-01-01 12:10:00');
        Carbon::setTestNow($revertMoment);

        try {
            $this->actingAs($user)
                ->postJson("/api/orders/{$order->id}/steps/{$step->id}/menus", [
                    'menu_id' => $additionalMenu->id,
                    'quantity' => 1,
                ])
                ->assertCreated();

            $order->refresh();

            self::assertSame(OrderStatus::PENDING, $order->status);
            self::assertNull($order->served_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_it_marks_step_menu_ready_without_updating_step_when_others_in_prep(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::IN_PREP,
        ]);

        $menuA = Menu::factory()->create(['company_id' => $user->company_id]);
        $menuB = Menu::factory()->create(['company_id' => $user->company_id]);

        $stepMenuA = StepMenu::factory()->for($step, 'step')->for($menuA)->create([
            'status' => StepMenuStatus::IN_PREP,
            'quantity' => 1,
        ]);

        StepMenu::factory()->for($step, 'step')->for($menuB)->create([
            'status' => StepMenuStatus::IN_PREP,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/step-menus/{$stepMenuA->id}/ready");

        $response->assertOk()
            ->assertJsonPath('step.status', OrderStepStatus::IN_PREP->value);

        $this->assertDatabaseHas('order_steps', [
            'id' => $step->id,
            'status' => OrderStepStatus::IN_PREP->value,
        ]);
    }

    public function test_it_returns_404_when_marking_menu_ready_outside_company(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $order = $this->createOrderForUser($otherUser);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::IN_PREP,
        ]);

        $menu = Menu::factory()->create(['company_id' => $otherUser->company_id]);

        $stepMenu = StepMenu::factory()->for($step, 'step')->for($menu)->create([
            'status' => StepMenuStatus::IN_PREP,
        ]);

        $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/step-menus/{$stepMenu->id}/ready")
            ->assertStatus(404);
    }

    public function test_it_marks_step_menu_served_and_updates_step_when_all_served(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::READY,
            'served_at' => null,
        ]);

        $servedMenu = Menu::factory()->create(['company_id' => $user->company_id]);
        $targetMenu = Menu::factory()->create(['company_id' => $user->company_id]);

        StepMenu::factory()->for($step, 'step')->for($servedMenu)->create([
            'status' => StepMenuStatus::SERVED,
            'quantity' => 1,
            'served_at' => now(),
        ]);

        $stepMenu = StepMenu::factory()->for($step, 'step')->for($targetMenu)->create([
            'status' => StepMenuStatus::READY,
            'quantity' => 2,
            'served_at' => null,
        ]);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/step-menus/{$stepMenu->id}/served");

        $response->assertOk()
            ->assertJsonPath('step_menu.id', $stepMenu->id)
            ->assertJsonPath('step_menu.status', StepMenuStatus::SERVED->value)
            ->assertJsonPath('step.status', OrderStepStatus::SERVED->value);

        self::assertNotNull($response->json('step_menu.served_at'));
        self::assertNotNull($response->json('step.served_at'));

        $this->assertDatabaseHas('step_menus', [
            'id' => $stepMenu->id,
            'status' => StepMenuStatus::SERVED->value,
        ]);

        $this->assertDatabaseHas('order_steps', [
            'id' => $step->id,
            'status' => OrderStepStatus::SERVED->value,
        ]);
    }

    public function test_it_marks_step_menu_served_without_updating_step_when_others_not_served(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::READY,
            'served_at' => null,
        ]);

        $menuA = Menu::factory()->create(['company_id' => $user->company_id]);
        $menuB = Menu::factory()->create(['company_id' => $user->company_id]);

        $stepMenuA = StepMenu::factory()->for($step, 'step')->for($menuA)->create([
            'status' => StepMenuStatus::READY,
            'quantity' => 1,
            'served_at' => null,
        ]);

        StepMenu::factory()->for($step, 'step')->for($menuB)->create([
            'status' => StepMenuStatus::READY,
            'quantity' => 1,
            'served_at' => null,
        ]);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/step-menus/{$stepMenuA->id}/served");

        $response->assertOk()
            ->assertJsonPath('step.status', OrderStepStatus::READY->value);

        self::assertNull($response->json('step.served_at'));

        $this->assertDatabaseHas('order_steps', [
            'id' => $step->id,
            'status' => OrderStepStatus::READY->value,
        ]);
    }

    public function test_it_rejects_marking_step_menu_served_when_not_ready(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::IN_PREP,
        ]);

        $menu = Menu::factory()->create(['company_id' => $user->company_id]);

        $stepMenu = StepMenu::factory()->for($step, 'step')->for($menu)->create([
            'status' => StepMenuStatus::IN_PREP,
            'served_at' => null,
        ]);

        $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/step-menus/{$stepMenu->id}/served")
            ->assertStatus(422);
    }

    public function test_it_returns_404_when_marking_menu_served_outside_company(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $order = $this->createOrderForUser($otherUser);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::READY,
        ]);

        $menu = Menu::factory()->create(['company_id' => $otherUser->company_id]);

        $stepMenu = StepMenu::factory()->for($step, 'step')->for($menu)->create([
            'status' => StepMenuStatus::READY,
            'served_at' => null,
        ]);

        $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/step-menus/{$stepMenu->id}/served")
            ->assertStatus(404);
    }

    public function test_it_marks_order_as_payed_when_all_menus_served(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user, [
            'status' => OrderStatus::SERVED,
            'served_at' => now(),
        ]);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::SERVED,
            'served_at' => now(),
        ]);

        $menu = Menu::factory()->create(['company_id' => $user->company_id]);

        StepMenu::factory()->for($step, 'step')->for($menu)->create([
            'status' => StepMenuStatus::SERVED,
            'served_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/pay");

        $response->assertOk()
            ->assertJsonPath('order.status', OrderStatus::PAYED->value);

        self::assertNotNull($response->json('order.payed_at'));

        $order->refresh();

        self::assertEquals(OrderStatus::PAYED, $order->status);
        self::assertNotNull($order->payed_at);
    }

    public function test_it_rejects_paying_order_when_menus_not_served(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::IN_PREP,
        ]);

        $menu = Menu::factory()->create(['company_id' => $user->company_id]);

        StepMenu::factory()->for($step, 'step')->for($menu)->create([
            'status' => StepMenuStatus::READY,
            'served_at' => null,
        ]);

        $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/pay")
            ->assertStatus(422);

        $order->refresh();

        self::assertEquals(OrderStatus::PENDING, $order->status);
        self::assertNull($order->payed_at);
    }

    public function test_it_marks_order_as_payed_when_forced_even_if_not_served(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::IN_PREP,
        ]);

        $menu = Menu::factory()->create(['company_id' => $user->company_id]);

        StepMenu::factory()->for($step, 'step')->for($menu)->create([
            'status' => StepMenuStatus::READY,
            'served_at' => null,
        ]);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/pay", [
            'force' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('order.status', OrderStatus::PAYED->value);

        $order->refresh();

        self::assertEquals(OrderStatus::PAYED, $order->status);
        self::assertNotNull($order->payed_at);
    }

    public function test_it_returns_404_when_paying_order_outside_company(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $order = $this->createOrderForUser($otherUser);

        $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/pay")
            ->assertStatus(404);
    }

    public function test_it_cancels_order_and_applies_loss_rules(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $location = Location::factory()->create(['company_id' => $user->company_id]);

        $prepIngredient = Ingredient::factory()->create([
            'company_id' => $user->company_id,
            'unit' => MeasurementUnit::GRAM,
        ]);
        $prepIngredient->locations()->syncWithoutDetaching([$location->id => ['quantity' => 300]]);

        $returnIngredient = Ingredient::factory()->create([
            'company_id' => $user->company_id,
            'unit' => MeasurementUnit::UNIT,
        ]);
        $returnIngredient->locations()->syncWithoutDetaching([$location->id => ['quantity' => 10]]);

        $lossIngredient = Ingredient::factory()->create([
            'company_id' => $user->company_id,
            'unit' => MeasurementUnit::UNIT,
        ]);
        $lossIngredient->locations()->syncWithoutDetaching([$location->id => ['quantity' => 6]]);

        $simpleMenu = Menu::factory()->create([
            'company_id' => $user->company_id,
            'service_type' => MenuServiceType::PREP,
        ]);

        $prepMenu = Menu::factory()->create([
            'company_id' => $user->company_id,
            'service_type' => MenuServiceType::PREP,
        ]);
        $prepMenu->items()->create([
            'entity_id' => $prepIngredient->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 30,
            'unit' => MeasurementUnit::GRAM,
        ]);

        $directReadyMenu = Menu::factory()->create([
            'company_id' => $user->company_id,
            'service_type' => MenuServiceType::DIRECT,
            'is_returnable' => false,
        ]);

        $directReturnMenu = Menu::factory()->create([
            'company_id' => $user->company_id,
            'service_type' => MenuServiceType::DIRECT,
            'is_returnable' => true,
        ]);
        $directReturnMenu->items()->create([
            'entity_id' => $returnIngredient->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => MeasurementUnit::UNIT,
        ]);

        $directLossMenu = Menu::factory()->create([
            'company_id' => $user->company_id,
            'service_type' => MenuServiceType::DIRECT,
            'is_returnable' => false,
        ]);
        $directLossMenu->items()->create([
            'entity_id' => $lossIngredient->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => MeasurementUnit::UNIT,
        ]);

        $stepA = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::READY,
        ]);

        $simpleStepMenu = StepMenu::factory()->for($stepA, 'step')->for($simpleMenu)->create([
            'quantity' => 1,
            'status' => StepMenuStatus::IN_PREP,
        ]);

        $prepStepMenu = StepMenu::factory()->for($stepA, 'step')->for($prepMenu)->create([
            'quantity' => 2,
            'status' => StepMenuStatus::READY,
        ]);

        $stepB = OrderStep::factory()->for($order)->create([
            'position' => 2,
            'status' => OrderStepStatus::SERVED,
            'served_at' => now(),
        ]);

        $directReadyStepMenu = StepMenu::factory()->for($stepB, 'step')->for($directReadyMenu)->create([
            'quantity' => 1,
            'status' => StepMenuStatus::READY,
        ]);

        $directReturnStepMenu = StepMenu::factory()->for($stepB, 'step')->for($directReturnMenu)->create([
            'quantity' => 2,
            'status' => StepMenuStatus::SERVED,
            'served_at' => now(),
        ]);

        $directLossStepMenu = StepMenu::factory()->for($stepB, 'step')->for($directLossMenu)->create([
            'quantity' => 3,
            'status' => StepMenuStatus::SERVED,
            'served_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel", [
            'unopened_returns' => [$directReturnStepMenu->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('order.status', OrderStatus::CANCELED->value)
            ->assertJsonPath('order.canceled_at', fn ($value) => $value !== null);

        $lossIds = $response->json('loss_step_menu_ids');
        $returnIds = $response->json('return_accepted_step_menu_ids');

        self::assertEqualsCanonicalizing([
            $prepStepMenu->id,
            $directLossStepMenu->id,
        ], $lossIds);

        self::assertEqualsCanonicalizing([
            $directReturnStepMenu->id,
        ], $returnIds);

        $order->refresh();
        self::assertEquals(OrderStatus::CANCELED, $order->status);
        self::assertNotNull($order->canceled_at);
        self::assertNull($order->served_at);
        self::assertNull($order->payed_at);

        $this->assertDatabaseMissing('step_menus', ['order_step_id' => $stepA->id]);
        $this->assertDatabaseMissing('step_menus', ['order_step_id' => $stepB->id]);

        $this->assertDatabaseHas('losses', [
            'loss_item_id' => $prepIngredient->id,
            'loss_item_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 60.0,
            'reason' => 'KITCHEN_LOSS',
        ]);

        $this->assertDatabaseHas('losses', [
            'loss_item_id' => $lossIngredient->id,
            'loss_item_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 3.0,
            'reason' => 'SERVICE_LOSS',
        ]);

        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $prepIngredient->id,
            'location_id' => $location->id,
            'quantity' => 240.0,
        ]);

        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $lossIngredient->id,
            'location_id' => $location->id,
            'quantity' => 3.0,
        ]);

        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $returnIngredient->id,
            'location_id' => $location->id,
            'quantity' => 10.0,
        ]);
    }
}
