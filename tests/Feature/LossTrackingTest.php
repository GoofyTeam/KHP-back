<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Preparation;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Suite de tests pour la gestion des pertes.
 *
 * Vérifie l'enregistrement, la validation et l'annulation des pertes
 * pour les ingrédients et les préparations.
 */
class LossTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Location $location;

    protected Ingredient $ingredient;

    protected Preparation $preparation;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->location = Location::factory()->create(['company_id' => $this->company->id]);
        $this->ingredient = Ingredient::factory()->create(['company_id' => $this->company->id]);
        $this->preparation = Preparation::factory()->create(['company_id' => $this->company->id]);
        $this->user = User::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($this->user);
    }

    /**
     * Enregistrer la perte d'un ingrédient met à jour le stock
     * et crée un mouvement de retrait.
     */
    public function test_recording_ingredient_loss_updates_stock_and_logs_history(): void
    {
        $this->ingredient->locations()->updateExistingPivot($this->location->id, ['quantity' => 10]);
        StockMovement::query()->delete();

        $response = $this->postJson('/api/losses', [
            'trackable_type' => 'ingredient',
            'trackable_id' => $this->ingredient->id,
            'location_id' => $this->location->id,
            'quantity' => 3.5,
            'reason' => 'Cassé',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('losses', [
            'lossable_id' => $this->ingredient->id,
            'lossable_type' => Ingredient::class,
            'location_id' => $this->location->id,
            'quantity' => 3.5,
            'reason' => 'Cassé',
        ]);

        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $this->ingredient->id,
            'location_id' => $this->location->id,
            'quantity' => 6.5,
        ]);

        $movement = StockMovement::where('trackable_id', $this->ingredient->id)->first();
        $this->assertEquals('withdrawal', $movement->type);
        $this->assertEquals(10, $movement->quantity_before);
        $this->assertEquals(6.5, $movement->quantity_after);
        $this->assertEquals(3.5, $movement->quantity);
    }

    /**
     * Enregistrer la perte d'une préparation met à jour le stock
     * et crée un mouvement de retrait.
     */
    public function test_recording_preparation_loss_updates_stock_and_logs_history(): void
    {
        $this->preparation->locations()->updateExistingPivot($this->location->id, ['quantity' => 5]);
        StockMovement::query()->delete();

        $response = $this->postJson('/api/losses', [
            'trackable_type' => 'preparation',
            'trackable_id' => $this->preparation->id,
            'location_id' => $this->location->id,
            'quantity' => 2,
            'reason' => 'Renversé',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('losses', [
            'lossable_id' => $this->preparation->id,
            'lossable_type' => Preparation::class,
            'location_id' => $this->location->id,
            'quantity' => 2.0,
            'reason' => 'Renversé',
        ]);

        $this->assertDatabaseHas('location_preparation', [
            'preparation_id' => $this->preparation->id,
            'location_id' => $this->location->id,
            'quantity' => 3.0,
        ]);

        $movement = StockMovement::where('trackable_id', $this->preparation->id)->first();
        $this->assertEquals('withdrawal', $movement->type);
        $this->assertEquals(5.0, $movement->quantity_before);
        $this->assertEquals(3.0, $movement->quantity_after);
        $this->assertEquals(2.0, $movement->quantity);
    }

    /**
     * Enregistre correctement une perte avec des quantités décimales proches.
     */
    public function test_recording_loss_with_decimal_precision(): void
    {
        $this->ingredient->locations()->updateExistingPivot($this->location->id, ['quantity' => 0.68]);
        StockMovement::query()->delete();

        $response = $this->postJson('/api/losses', [
            'trackable_type' => 'ingredient',
            'trackable_id' => $this->ingredient->id,
            'location_id' => $this->location->id,
            'quantity' => 0.67,
            'reason' => 'Test',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $this->ingredient->id,
            'location_id' => $this->location->id,
            'quantity' => 0.01,
        ]);
    }

    /**
     * Empêche l'enregistrement d'une perte si le stock est insuffisant.
     */
    public function test_recording_loss_with_insufficient_stock_returns_error(): void
    {
        $this->ingredient->locations()->updateExistingPivot($this->location->id, ['quantity' => 2]);
        StockMovement::query()->delete();

        $response = $this->postJson('/api/losses', [
            'trackable_type' => 'ingredient',
            'trackable_id' => $this->ingredient->id,
            'location_id' => $this->location->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(400);

        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $this->ingredient->id,
            'location_id' => $this->location->id,
            'quantity' => 2,
        ]);

        $this->assertDatabaseCount('stock_movements', 0);
    }

    /**
     * Annuler une perte restaure le stock et enregistre un mouvement inverse.
     */
    public function test_cancelling_loss_restores_stock_and_logs_history(): void
    {
        $this->ingredient->locations()->updateExistingPivot($this->location->id, ['quantity' => 5]);
        StockMovement::query()->delete();

        $storeResponse = $this->postJson('/api/losses', [
            'trackable_type' => 'ingredient',
            'trackable_id' => $this->ingredient->id,
            'location_id' => $this->location->id,
            'quantity' => 2,
        ]);

        $lossId = $storeResponse->json('loss.id');

        $cancelResponse = $this->deleteJson('/api/losses/rollback/'.$lossId);
        $cancelResponse->assertStatus(200);

        $this->assertDatabaseMissing('losses', ['id' => $lossId]);
        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $this->ingredient->id,
            'location_id' => $this->location->id,
            'quantity' => 5,
        ]);

        $movements = StockMovement::where('trackable_id', $this->ingredient->id)->get();
        $this->assertCount(2, $movements);
        $this->assertNotNull($movements->firstWhere('type', 'withdrawal'));
        $this->assertNotNull($movements->firstWhere('type', 'addition'));
    }
}
