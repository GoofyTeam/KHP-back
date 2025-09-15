<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RoomControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;

    protected $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
    }

    public function test_store_creates_room()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/rooms', [
            'name' => 'Salle A',
            'code' => 'A',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Salle A')
            ->assertJsonPath('data.code', 'A');

        $this->assertDatabaseHas('rooms', [
            'name' => 'Salle A',
            'code' => 'A',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_update_room()
    {
        $this->actingAs($this->user);

        $room = Room::factory()->create([
            'name' => 'Salle A',
            'code' => 'A',
            'company_id' => $this->company->id,
        ]);

        $response = $this->putJson('/api/rooms/'.$room->id, [
            'name' => 'Salle B',
            'code' => 'B',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Salle B')
            ->assertJsonPath('data.code', 'B');

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'name' => 'Salle B',
            'code' => 'B',
        ]);
    }

    public function test_destroy_room()
    {
        $this->actingAs($this->user);

        $room = Room::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $response = $this->deleteJson('/api/rooms/'.$room->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('rooms', [
            'id' => $room->id,
        ]);
    }
}
