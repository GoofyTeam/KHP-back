<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Room;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TableControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;

    protected $company;

    protected $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->room = Room::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'A',
        ]);
    }

    public function test_store_creates_multiple_tables_with_auto_labels()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/rooms/'.$this->room->id.'/tables', [
            'count' => 3,
            'seats' => 4,
        ]);

        $response->assertStatus(201)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.label', 'A1')
            ->assertJsonPath('data.1.label', 'A2')
            ->assertJsonPath('data.2.label', 'A3');

        $this->assertDatabaseHas('tables', [
            'room_id' => $this->room->id,
            'label' => 'A1',
            'seats' => 4,
        ]);
        $this->assertDatabaseHas('tables', [
            'room_id' => $this->room->id,
            'label' => 'A3',
        ]);
    }

    public function test_update_table()
    {
        $this->actingAs($this->user);

        $table = Table::factory()->create([
            'room_id' => $this->room->id,
            'company_id' => $this->company->id,
            'label' => 'A1',
            'seats' => 2,
        ]);

        $response = $this->putJson('/api/rooms/'.$this->room->id.'/tables/'.$table->id, [
            'label' => 'A5',
            'seats' => 6,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.label', 'A5')
            ->assertJsonPath('data.seats', 6);

        $this->assertDatabaseHas('tables', [
            'id' => $table->id,
            'label' => 'A5',
            'seats' => 6,
        ]);
    }

    public function test_destroy_table()
    {
        $this->actingAs($this->user);

        $table = Table::factory()->create([
            'room_id' => $this->room->id,
            'company_id' => $this->company->id,
        ]);

        $response = $this->deleteJson('/api/rooms/'.$this->room->id.'/tables/'.$table->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tables', [
            'id' => $table->id,
        ]);
    }
}
