<?php

namespace Tests\Feature;

use App\Enums\MenuServiceType;
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

    public function test_it_syncs_step_menus_with_add_update_and_remove(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::SERVED,
            'served_at' => now(),
        ]);

        $servedMenu = Menu::factory()->create(['company_id' => $user->company_id]);
        $readyMenu = Menu::factory()->create(['company_id' => $user->company_id]);

        $servedStepMenu = StepMenu::factory()->for($step, 'step')->for($servedMenu)->create([
            'status' => StepMenuStatus::SERVED,
            'quantity' => 1,
            'note' => 'Initial note',
            'served_at' => now(),
        ]);

        $readyStepMenu = StepMenu::factory()->for($step, 'step')->for($readyMenu)->create([
            'status' => StepMenuStatus::READY,
            'quantity' => 2,
        ]);

        $newMenu = Menu::factory()->create([
            'company_id' => $user->company_id,
            'service_type' => MenuServiceType::PREP,
        ]);

        $response = $this->actingAs($user)->putJson("/api/orders/{$order->id}/steps/{$step->id}/menus", [
            'menus' => [
                ['step_menu_id' => $servedStepMenu->id, 'quantity' => 3, 'note' => 'Updated note'],
                ['step_menu_id' => $readyStepMenu->id, 'quantity' => 0],
                ['menu_id' => $newMenu->id, 'quantity' => 2, 'note' => 'Extra spicy'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('step.id', $step->id)
            ->assertJsonPath('message', 'Step menus updated successfully.')
            ->assertJsonPath('step.status', OrderStepStatus::IN_PREP->value);

        $step->refresh();

        self::assertNull($step->served_at);
        self::assertSame(OrderStepStatus::IN_PREP, $step->status);

        $this->assertDatabaseHas('step_menus', [
            'id' => $servedStepMenu->id,
            'quantity' => 3,
            'note' => 'Updated note',
            'status' => StepMenuStatus::SERVED->value,
        ]);

        $this->assertDatabaseMissing('step_menus', [
            'id' => $readyStepMenu->id,
        ]);

        $this->assertDatabaseHas('step_menus', [
            'order_step_id' => $step->id,
            'menu_id' => $newMenu->id,
            'quantity' => 2,
            'status' => StepMenuStatus::IN_PREP->value,
            'note' => 'Extra spicy',
        ]);
    }

    public function test_it_syncs_step_menus_with_direct_menu_and_keeps_step_ready(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::READY,
        ]);

        $existingMenu = Menu::factory()->create(['company_id' => $user->company_id]);

        $readyStepMenu = StepMenu::factory()->for($step, 'step')->for($existingMenu)->create([
            'status' => StepMenuStatus::READY,
            'quantity' => 1,
        ]);

        $directMenu = Menu::factory()->create([
            'company_id' => $user->company_id,
            'service_type' => MenuServiceType::DIRECT,
        ]);

        $response = $this->actingAs($user)->putJson("/api/orders/{$order->id}/steps/{$step->id}/menus", [
            'menus' => [
                ['step_menu_id' => $readyStepMenu->id, 'quantity' => 2],
                ['menu_id' => $directMenu->id, 'quantity' => 1],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('step.status', OrderStepStatus::READY->value);

        $this->assertDatabaseHas('step_menus', [
            'id' => $readyStepMenu->id,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('step_menus', [
            'order_step_id' => $step->id,
            'menu_id' => $directMenu->id,
            'status' => StepMenuStatus::READY->value,
            'quantity' => 1,
        ]);

        $step->refresh();

        self::assertNull($step->served_at);
        self::assertSame(OrderStepStatus::READY, $step->status);
    }

    public function test_it_returns_404_when_syncing_step_menus_outside_company(): void
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
            ->putJson("/api/orders/{$order->id}/steps/{$step->id}/menus", [
                'menus' => [
                    ['menu_id' => $menu->id, 'quantity' => 1],
                ],
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
            ->putJson("/api/orders/{$order->id}/steps/{$step->id}/menus", [
                'menus' => [
                    ['menu_id' => $menu->id, 'quantity' => 1],
                ],
            ])
            ->assertStatus(404);
    }

    public function test_it_requires_quantity_when_creating_step_menu_during_sync(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user);

        $step = OrderStep::factory()->for($order)->create([
            'position' => 1,
            'status' => OrderStepStatus::IN_PREP,
        ]);

        $menu = Menu::factory()->create(['company_id' => $user->company_id]);

        $this->actingAs($user)
            ->putJson("/api/orders/{$order->id}/steps/{$step->id}/menus", [
                'menus' => [
                    ['menu_id' => $menu->id],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['menus.0.quantity']);
    }

    public function test_it_rejects_negative_quantity_when_updating_step_menu(): void
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
            'quantity' => 1,
        ]);

        $this->actingAs($user)
            ->putJson("/api/orders/{$order->id}/steps/{$step->id}/menus", [
                'menus' => [
                    ['step_menu_id' => $stepMenu->id, 'quantity' => -2],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['menus.0.quantity']);
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
}
