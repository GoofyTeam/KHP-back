<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_and_remove_record_movements_with_reason(): void
    {
        $company = Company::factory()->create();
        $location = Location::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $this->actingAs($user);

        $service = app(StockService::class);
        $service->add($ingredient, $location->id, $company->id, 5);

        $movement = StockMovement::latest()->first();
        $this->assertEquals('addition', $movement->type);
        $this->assertEquals(StockService::DEFAULT_ADD_REASON, $movement->reason);
        $this->assertEquals(0, $movement->quantity_before);
        $this->assertEquals(5, $movement->quantity_after);

        $service->remove($ingredient, $location->id, $company->id, 2);

        $movement = StockMovement::orderByDesc('id')->first();
        $this->assertEquals('withdrawal', $movement->type);
        $this->assertEquals(StockService::DEFAULT_REMOVE_REASON, $movement->reason);
        $this->assertEquals(5, $movement->quantity_before);
        $this->assertEquals(3, $movement->quantity_after);
    }

    public function test_move_generates_reason(): void
    {
        $company = Company::factory()->create();
        $from = Location::factory()->create(['company_id' => $company->id]);
        $to = Location::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $this->actingAs($user);

        // seed starting quantity at from location
        $ingredient->locations()->syncWithoutDetaching([$from->id => ['quantity' => 5]]);

        $service = app(StockService::class);
        $service->move($ingredient, $from->id, $to->id, $company->id, 2);

        $reason = "Moved from {$from->name} to {$to->name}";

        $movements = StockMovement::orderBy('id')->get();
        $this->assertCount(2, $movements);

        $sourceMovement = $movements->firstWhere('location_id', $from->id);
        $this->assertNotNull($sourceMovement);
        $this->assertEquals('movement', $sourceMovement->type);
        $this->assertEquals($reason, $sourceMovement->reason);
        $this->assertEquals(5, $sourceMovement->quantity_before);
        $this->assertEquals(3, $sourceMovement->quantity_after);

        $destMovement = $movements->firstWhere('location_id', $to->id);
        $this->assertNotNull($destMovement);
        $this->assertEquals('movement', $destMovement->type);
        $this->assertEquals($reason, $destMovement->reason);
        $this->assertEquals(0, $destMovement->quantity_before);
        $this->assertEquals(2, $destMovement->quantity_after);
    }
}
