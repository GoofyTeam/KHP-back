<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class RoomQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_it_lists_rooms_for_company(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Room::factory()->for($user->company)->create(['name' => 'Alpha']);
        Room::factory()->for($other->company)->create(['name' => 'Beta']);

        $response = $this->actingAs($user)->graphQL(/** @lang GraphQL */ '{
            rooms {
                data { name }
            }
        }');

        $response->assertJsonCount(1, 'data.rooms.data');
        $response->assertJsonFragment(['name' => 'Alpha']);
        $response->assertJsonMissing(['name' => 'Beta']);
    }

    public function test_it_filters_rooms_by_search(): void
    {
        $user = User::factory()->create();
        Room::factory()->for($user->company)->create(['name' => 'Blue Room']);
        Room::factory()->for($user->company)->create(['name' => 'Red Room']);

        $query = /** @lang GraphQL */ 'query ($search: String) {
            rooms(search: $search) {
                data { name }
            }
        }';

        $response = $this->actingAs($user)->graphQL($query, ['search' => 'blu']);

        $response->assertJsonCount(1, 'data.rooms.data');
        $response->assertJsonFragment(['name' => 'Blue Room']);
        $response->assertJsonMissing(['name' => 'Red Room']);
    }

    public function test_it_filters_rooms_by_code(): void
    {
        $user = User::factory()->create();
        Room::factory()->for($user->company)->create(['code' => 'A']);
        Room::factory()->for($user->company)->create(['code' => 'B']);

        $query = /** @lang GraphQL */ 'query ($code: String) {
            rooms(code: $code) {
                data { code }
            }
        }';

        $response = $this->actingAs($user)->graphQL($query, ['code' => 'A']);

        $response->assertJsonCount(1, 'data.rooms.data');
        $response->assertJsonFragment(['code' => 'A']);
        $response->assertJsonMissing(['code' => 'B']);
    }

    public function test_it_orders_rooms_by_name_desc(): void
    {
        $user = User::factory()->create();
        Room::factory()->for($user->company)->create(['name' => 'A']);
        Room::factory()->for($user->company)->create(['name' => 'B']);

        $response = $this->actingAs($user)->graphQL(/** @lang GraphQL */ '
            {
                rooms(orderBy: [{column: NAME, order: DESC}]) {
                    data { name }
                }
            }
        ');

        $response->assertJsonPath('data.rooms.data.0.name', 'B');
        $response->assertJsonPath('data.rooms.data.1.name', 'A');
    }

    public function test_it_fetches_room_by_id(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->for($user->company)->create();

        $query = /** @lang GraphQL */ 'query ($id: ID!) {
            room(id: $id) { id }
        }';

        $response = $this->actingAs($user)->graphQL($query, ['id' => $room->id]);

        $response->assertJsonFragment(['id' => (string) $room->id]);
    }

    public function test_it_fetches_room_by_code(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->for($user->company)->create(['code' => 'A']);

        $query = /** @lang GraphQL */ 'query ($code: String!) {
            room(code: $code) { code }
        }';

        $response = $this->actingAs($user)->graphQL($query, ['code' => 'A']);

        $response->assertJsonFragment(['code' => 'A']);
    }
}
