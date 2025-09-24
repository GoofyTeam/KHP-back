<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Room;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class TableQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_it_lists_tables_for_company(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $room = Room::factory()->for($user->company)->create();
        Table::factory()->for($room)->for($user->company)->create(['label' => 'A1']);
        Table::factory()->for(Room::factory()->for($other->company))->for($other->company)->create(['label' => 'B1']);

        $response = $this->actingAs($user)->graphQL(/** @lang GraphQL */ '{
            tables { data { label } }
        }');

        $response->assertJsonCount(1, 'data.tables.data');
        $response->assertJsonFragment(['label' => 'A1']);
        $response->assertJsonMissing(['label' => 'B1']);
    }

    public function test_it_filters_tables_by_search(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->for($user->company)->create();
        Table::factory()->for($room)->for($user->company)->create(['label' => 'T1']);
        Table::factory()->for($room)->for($user->company)->create(['label' => 'T2']);

        $query = /** @lang GraphQL */ 'query ($search: String) {
            tables(search: $search) { data { label } }
        }';

        $response = $this->actingAs($user)->graphQL($query, ['search' => 't1']);

        $response->assertJsonCount(1, 'data.tables.data');
        $response->assertJsonFragment(['label' => 'T1']);
        $response->assertJsonMissing(['label' => 'T2']);
    }

    public function test_it_filters_tables_by_room_id(): void
    {
        $user = User::factory()->create();
        $roomA = Room::factory()->for($user->company)->create();
        $roomB = Room::factory()->for($user->company)->create();
        Table::factory()->for($roomA)->for($user->company)->create(['label' => 'A1']);
        Table::factory()->for($roomB)->for($user->company)->create(['label' => 'B1']);

        $query = /** @lang GraphQL */ 'query ($roomId: ID) {
            tables(roomId: $roomId) { data { label } }
        }';

        $response = $this->actingAs($user)->graphQL($query, ['roomId' => $roomA->id]);

        $response->assertJsonCount(1, 'data.tables.data');
        $response->assertJsonFragment(['label' => 'A1']);
        $response->assertJsonMissing(['label' => 'B1']);
    }

    public function test_it_orders_tables_by_label_desc(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->for($user->company)->create();
        Table::factory()->for($room)->for($user->company)->create(['label' => 'A']);
        Table::factory()->for($room)->for($user->company)->create(['label' => 'B']);

        $response = $this->actingAs($user)->graphQL(/** @lang GraphQL */ '{
            tables(orderBy: [{column: LABEL, order: DESC}]) { data { label } }
        }');

        $response->assertJsonPath('data.tables.data.0.label', 'B');
        $response->assertJsonPath('data.tables.data.1.label', 'A');
    }

    public function test_it_fetches_table_by_id(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->for($user->company)->create();
        $table = Table::factory()->for($room)->for($user->company)->create();

        $query = /** @lang GraphQL */ 'query ($id: ID!) {
            table(id: $id) { id }
        }';

        $response = $this->actingAs($user)->graphQL($query, ['id' => $table->id]);

        $response->assertJsonFragment(['id' => (string) $table->id]);
    }

    public function test_orders_field_returns_null_when_table_has_no_orders(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->for($user->company)->create();
        $table = Table::factory()->for($room)->for($user->company)->create();

        $query = /** @lang GraphQL */ 'query ($id: ID!) {
            table(id: $id) {
                orders { id }
            }
        }';

        $response = $this->actingAs($user)->graphQL($query, ['id' => $table->id]);

        $response->assertJsonPath('data.table.orders', null);
    }

    public function test_orders_field_returns_active_orders_for_table(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->for($user->company)->create();
        $table = Table::factory()->for($room)->for($user->company)->create();

        $pendingOrder = Order::factory()
            ->for($table, 'table')
            ->for($user->company, 'company')
            ->for($user, 'user')
            ->create();

        $servedOrder = Order::factory()
            ->for($table, 'table')
            ->for($user->company, 'company')
            ->for($user, 'user')
            ->served()
            ->create();

        $query = /** @lang GraphQL */ 'query ($id: ID!) {
            table(id: $id) {
                orders { id status }
            }
        }';

        $response = $this->actingAs($user)->graphQL($query, ['id' => $table->id]);

        $response->assertJsonCount(2, 'data.table.orders');
        $response->assertJsonFragment([
            'id' => (string) $pendingOrder->id,
            'status' => $pendingOrder->status->value,
        ]);
        $response->assertJsonFragment([
            'id' => (string) $servedOrder->id,
            'status' => $servedOrder->status->value,
        ]);
    }

    public function test_orders_field_returns_null_when_only_finalized_orders(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->for($user->company)->create();
        $table = Table::factory()->for($room)->for($user->company)->create();

        Order::factory()
            ->for($table, 'table')
            ->for($user->company, 'company')
            ->for($user, 'user')
            ->payed()
            ->create();

        Order::factory()
            ->for($table, 'table')
            ->for($user->company, 'company')
            ->for($user, 'user')
            ->canceled()
            ->create();

        $query = /** @lang GraphQL */ 'query ($id: ID!) {
            table(id: $id) {
                orders { id }
            }
        }';

        $response = $this->actingAs($user)->graphQL($query, ['id' => $table->id]);

        $response->assertJsonPath('data.table.orders', null);
    }
}
