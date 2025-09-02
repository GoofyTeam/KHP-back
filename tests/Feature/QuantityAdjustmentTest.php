<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\Preparation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuantityAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    /** Scénario : ajustement de la quantité d'un ingrédient avec suivi des périssables. */
    public function test_it_adjusts_ingredient_quantity(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $locationType = LocationType::factory()->create();
        $location = Location::factory()->create([
            'company_id' => $company->id,
            'location_type_id' => $locationType->id,
        ]);
        $category = Category::factory()->create(['company_id' => $company->id]);
        $category->locationTypes()->attach($locationType->id, ['shelf_life_hours' => 24]);
        $ingredient = Ingredient::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
        ]);
        $ingredient->locations()->updateExistingPivot($location->id, ['quantity' => 5]);

        $this->actingAs($user)
            ->postJson("/api/ingredients/{$ingredient->id}/add-quantity", [
                'location_id' => $location->id,
                'quantity' => 7.5,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ingredient->id,
            'location_id' => $location->id,
            'quantity' => 12.5,
        ]);

        $this->assertDatabaseHas('perishables', [
            'ingredient_id' => $ingredient->id,
            'location_id' => $location->id,
            'company_id' => $company->id,
            'quantity' => 7.5,
        ]);

        $this->actingAs($user)
            ->postJson("/api/ingredients/{$ingredient->id}/remove-quantity", [
                'location_id' => $location->id,
                'quantity' => 2,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('perishables', [
            'ingredient_id' => $ingredient->id,
            'location_id' => $location->id,
            'company_id' => $company->id,
            'quantity' => 5.5,
        ]);
    }

    /** Scénario : aucun enregistrement périssable si la durée de vie est absente. */
    public function test_it_skips_perishable_when_no_shelf_life(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $locationType = LocationType::factory()->create();
        $location = Location::factory()->create([
            'company_id' => $company->id,
            'location_type_id' => $locationType->id,
        ]);
        $category = Category::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
        ]);
        $ingredient->locations()->updateExistingPivot($location->id, ['quantity' => 5]);

        $this->actingAs($user)
            ->postJson("/api/ingredients/{$ingredient->id}/add-quantity", [
                'location_id' => $location->id,
                'quantity' => 2,
            ])
            ->assertStatus(200);

        $this->assertDatabaseCount('perishables', 0);
    }

    /** Scénario : rejet lorsqu'un ajustement rend la quantité négative. */
    public function test_it_prevents_negative_ingredient_quantity(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id]);
        $ingredient->locations()->updateExistingPivot($location->id, ['quantity' => 5]);

        $this->actingAs($user)
            ->postJson("/api/ingredients/{$ingredient->id}/remove-quantity", [
                'location_id' => $location->id,
                'quantity' => 10,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ingredient->id,
            'location_id' => $location->id,
            'quantity' => 5,
        ]);
    }

    /** Scénario : ajustement de la quantité d'une préparation. */
    public function test_it_adjusts_preparation_quantity(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);
        $preparation = Preparation::factory()->create(['company_id' => $company->id]);
        $preparation->locations()->updateExistingPivot($location->id, ['quantity' => 2]);

        $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/add-quantity", [
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

    /** Scénario : refus d'une quantité négative pour une préparation. */
    public function test_it_prevents_negative_preparation_quantity(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);
        $preparation = Preparation::factory()->create(['company_id' => $company->id]);
        $preparation->locations()->updateExistingPivot($location->id, ['quantity' => 3]);

        $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/remove-quantity", [
                'location_id' => $location->id,
                'quantity' => 5,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('location_preparation', [
            'preparation_id' => $preparation->id,
            'location_id' => $location->id,
            'quantity' => 3,
        ]);
    }

    /** Scénario : déplacement de la quantité d'un ingrédient entre deux emplacements. */
    public function test_it_moves_ingredient_quantity_between_locations(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $locationType = LocationType::factory()->create();
        $from = Location::factory()->create(['company_id' => $company->id, 'location_type_id' => $locationType->id]);
        $to = Location::factory()->create(['company_id' => $company->id, 'location_type_id' => $locationType->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);
        $category->locationTypes()->attach($locationType->id, ['shelf_life_hours' => 24]);
        $ingredient = Ingredient::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
        ]);
        $ingredient->locations()->updateExistingPivot($from->id, ['quantity' => 5]);
        $ingredient->locations()->updateExistingPivot($to->id, ['quantity' => 1]);
        app(\App\Services\PerishableService::class)->add($ingredient->id, $from->id, $company->id, 5);

        $this->actingAs($user)
            ->postJson("/api/ingredients/{$ingredient->id}/move-quantity", [
                'from_location_id' => $from->id,
                'to_location_id' => $to->id,
                'quantity' => 3,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ingredient->id,
            'location_id' => $from->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ingredient->id,
            'location_id' => $to->id,
            'quantity' => 4,
        ]);
        $this->assertDatabaseHas('perishables', [
            'ingredient_id' => $ingredient->id,
            'location_id' => $from->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('perishables', [
            'ingredient_id' => $ingredient->id,
            'location_id' => $to->id,
            'quantity' => 3,
        ]);

        $reason = "Moved from {$from->name} to {$to->name}";
        $this->assertDatabaseHas('stock_movements', [
            'trackable_id' => $ingredient->id,
            'trackable_type' => Ingredient::class,
            'location_id' => $from->id,
            'type' => 'withdrawal',
            'reason' => $reason,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'trackable_id' => $ingredient->id,
            'trackable_type' => Ingredient::class,
            'location_id' => $to->id,
            'type' => 'addition',
            'reason' => $reason,
        ]);
    }

    /** Scénario : déplacement de la quantité d'une préparation entre deux emplacements. */
    public function test_it_moves_preparation_quantity_between_locations(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $from = Location::factory()->create(['company_id' => $company->id]);
        $to = Location::factory()->create(['company_id' => $company->id]);
        $preparation = Preparation::factory()->create(['company_id' => $company->id]);
        $preparation->locations()->updateExistingPivot($from->id, ['quantity' => 5]);
        $preparation->locations()->updateExistingPivot($to->id, ['quantity' => 1]);

        $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/move-quantity", [
                'from_location_id' => $from->id,
                'to_location_id' => $to->id,
                'quantity' => 4,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('location_preparation', [
            'preparation_id' => $preparation->id,
            'location_id' => $from->id,
            'quantity' => 1,
        ]);
        $this->assertDatabaseHas('location_preparation', [
            'preparation_id' => $preparation->id,
            'location_id' => $to->id,
            'quantity' => 5,
        ]);

        $reason = "Moved from {$from->name} to {$to->name}";
        $this->assertDatabaseHas('stock_movements', [
            'trackable_id' => $preparation->id,
            'trackable_type' => Preparation::class,
            'location_id' => $from->id,
            'type' => 'withdrawal',
            'reason' => $reason,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'trackable_id' => $preparation->id,
            'trackable_type' => Preparation::class,
            'location_id' => $to->id,
            'type' => 'addition',
            'reason' => $reason,
        ]);
    }
}
