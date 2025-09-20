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

class StepMenuQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $orderAttributes
     * @param  array<string, mixed>  $stepAttributes
     */
    private function createOrderStepForUser(User $user, array $orderAttributes = [], array $stepAttributes = []): OrderStep
    {
        $room = Room::factory()->for($user->company)->create();
        $table = Table::factory()->for($room, 'room')->for($user->company)->create();

        $order = Order::create(array_merge([
            'table_id' => $table->id,
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'status' => OrderStatus::PENDING,
        ], $orderAttributes));

        return OrderStep::create(array_merge([
            'order_id' => $order->id,
            'position' => 1,
            'status' => OrderStepStatus::IN_PREP,
        ], $stepAttributes));
    }

    public function test_it_lists_step_menus_for_company(): void
    {
        $user = User::factory()->create();
        $step = $this->createOrderStepForUser($user);
        $menu = Menu::factory()->for($user->company)->create();

        $line = StepMenu::create([
            'order_step_id' => $step->id,
            'menu_id' => $menu->id,
            'quantity' => 1,
            'status' => StepMenuStatus::IN_PREP,
        ]);

        $otherUser = User::factory()->create();
        $otherStep = $this->createOrderStepForUser($otherUser);
        $otherMenu = Menu::factory()->for($otherUser->company)->create();

        StepMenu::create([
            'order_step_id' => $otherStep->id,
            'menu_id' => $otherMenu->id,
            'quantity' => 1,
            'status' => StepMenuStatus::READY,
        ]);

        $query = /** @lang GraphQL */ 'query ($stepId: ID!) {
            stepMenus(order_step_id: $stepId) {
                data { id }
            }
        }';

        $response = $this->actingAs($user)->graphQL($query, ['stepId' => $step->id]);

        $response->assertJsonCount(1, 'data.stepMenus.data');
        $response->assertJsonFragment(['id' => (string) $line->id]);
    }

    public function test_it_filters_step_menus_by_status(): void
    {
        $user = User::factory()->create();
        $step = $this->createOrderStepForUser($user);
        $menu = Menu::factory()->for($user->company)->create();

        $ready = StepMenu::create([
            'order_step_id' => $step->id,
            'menu_id' => $menu->id,
            'quantity' => 2,
            'status' => StepMenuStatus::READY,
        ]);

        StepMenu::create([
            'order_step_id' => $step->id,
            'menu_id' => $menu->id,
            'quantity' => 1,
            'status' => StepMenuStatus::IN_PREP,
        ]);

        $query = /** @lang GraphQL */ 'query ($statuses: [StepMenuStatusEnum!]) {
            stepMenus(statuses: $statuses) {
                data { id status }
            }
        }';

        $response = $this->actingAs($user)->graphQL($query, [
            'statuses' => [StepMenuStatus::READY->value],
        ]);

        $response->assertJsonCount(1, 'data.stepMenus.data');
        $response->assertJsonFragment([
            'id' => (string) $ready->id,
            'status' => StepMenuStatus::READY->value,
        ]);
    }

    public function test_it_orders_step_menus_by_quantity_desc(): void
    {
        $user = User::factory()->create();
        $step = $this->createOrderStepForUser($user);
        $menuA = Menu::factory()->for($user->company)->create();
        $menuB = Menu::factory()->for($user->company)->create();

        $small = StepMenu::create([
            'order_step_id' => $step->id,
            'menu_id' => $menuA->id,
            'quantity' => 1,
            'status' => StepMenuStatus::IN_PREP,
        ]);

        $large = StepMenu::create([
            'order_step_id' => $step->id,
            'menu_id' => $menuB->id,
            'quantity' => 4,
            'status' => StepMenuStatus::IN_PREP,
        ]);

        $response = $this->actingAs($user)->graphQL(/** @lang GraphQL */ '{
            stepMenus(orderBy: [{column: QUANTITY, order: DESC}]) {
                data { id }
            }
        }');

        $response->assertJsonPath('data.stepMenus.data.0.id', (string) $large->id);
        $response->assertJsonPath('data.stepMenus.data.1.id', (string) $small->id);
    }

    public function test_it_fetches_step_menu_by_id(): void
    {
        $user = User::factory()->create();
        $step = $this->createOrderStepForUser($user);
        $menu = Menu::factory()->for($user->company)->create();

        $line = StepMenu::create([
            'order_step_id' => $step->id,
            'menu_id' => $menu->id,
            'quantity' => 3,
            'status' => StepMenuStatus::SERVED,
        ]);

        $query = /** @lang GraphQL */ 'query ($id: ID!) {
            stepMenu(id: $id) { id status quantity }
        }';

        $response = $this->actingAs($user)->graphQL($query, ['id' => $line->id]);

        $response->assertJsonPath('data.stepMenu.id', (string) $line->id);
        $response->assertJsonPath('data.stepMenu.status', StepMenuStatus::SERVED->value);
        $response->assertJsonPath('data.stepMenu.quantity', 3);
    }
}
