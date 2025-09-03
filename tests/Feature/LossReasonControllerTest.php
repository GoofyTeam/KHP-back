<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\LossReason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LossReasonControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_store_creates_reason(): void
    {
        $this->actingAs($this->user);
        $name = 'Reason '.uniqid();

        $response = $this->postJson('/api/loss-reasons', [
            'name' => $name,
        ]);

        $response->assertStatus(201)->assertJsonPath('data.name', $name);

        $this->assertDatabaseHas('loss_reasons', [
            'name' => $name,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_update_changes_reason_name(): void
    {
        $this->actingAs($this->user);
        $reason = LossReason::factory()->create(['company_id' => $this->company->id]);
        $newName = 'Updated '.uniqid();

        $response = $this->putJson("/api/loss-reasons/{$reason->id}", [
            'name' => $newName,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.name', $newName);
        $this->assertDatabaseHas('loss_reasons', [
            'id' => $reason->id,
            'name' => $newName,
        ]);
    }

    public function test_destroy_removes_reason(): void
    {
        $this->actingAs($this->user);
        $reason = LossReason::factory()->create(['company_id' => $this->company->id]);

        $response = $this->deleteJson("/api/loss-reasons/{$reason->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('loss_reasons', [
            'id' => $reason->id,
        ]);
    }
}
