<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Preparation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuantityAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adjusts_ingredient_quantity(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id]);
        $ingredient->locations()->updateExistingPivot($location->id, ['quantity' => 5]);

        $this->actingAs($user)
            ->postJson("/api/ingredients/{$ingredient->id}/adjust-quantity", [
                'location_id' => $location->id,
                'quantity' => 7.5,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ingredient->id,
            'location_id' => $location->id,
            'quantity' => 12.5,
        ]);
    }

    public function test_it_prevents_negative_ingredient_quantity(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id]);
        $ingredient->locations()->updateExistingPivot($location->id, ['quantity' => 5]);

        $this->actingAs($user)
            ->postJson("/api/ingredients/{$ingredient->id}/adjust-quantity", [
                'location_id' => $location->id,
                'quantity' => -10,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ingredient->id,
            'location_id' => $location->id,
            'quantity' => 5,
        ]);
    }

    public function test_it_adjusts_preparation_quantity(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);
        $preparation = Preparation::factory()->create(['company_id' => $company->id]);
        $preparation->locations()->updateExistingPivot($location->id, ['quantity' => 2]);

        $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/adjust-quantity", [
                'location_id' => $location->id,
                'quantity' => 5,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('location_preparation', [
            'preparation_id' => $preparation->id,
            'location_id' => $location->id,
            'quantity' => 7,
        ]);
    }

    public function test_it_prevents_negative_preparation_quantity(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);
        $preparation = Preparation::factory()->create(['company_id' => $company->id]);
        $preparation->locations()->updateExistingPivot($location->id, ['quantity' => 3]);

        $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/adjust-quantity", [
                'location_id' => $location->id,
                'quantity' => -5,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('location_preparation', [
            'preparation_id' => $preparation->id,
            'location_id' => $location->id,
            'quantity' => 3,
        ]);
    }
}
