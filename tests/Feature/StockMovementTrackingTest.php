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
 * Cette suite de tests vérifie que les mouvements de stock sont automatiquement
 * enregistrés lorsque les quantités d'ingrédients ou de préparations sont modifiées
 * dans un emplacement.
 */
class StockMovementTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected $company;

    protected $location;

    protected $ingredient;

    protected $preparation;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer les données de test
        $this->company = Company::factory()->create();
        $this->location = Location::factory()->create(['company_id' => $this->company->id]);
        $this->ingredient = Ingredient::factory()->create(['company_id' => $this->company->id]);
        $this->preparation = Preparation::factory()->create(['company_id' => $this->company->id]);
        $this->user = User::factory()->create(['company_id' => $this->company->id]);

        // Authentifier l'utilisateur
        $this->actingAs($this->user);
    }

    /**
     * Test que l'ajout d'un ingrédient à un emplacement enregistre un mouvement de stock.
     */
    public function test_adding_ingredient_to_location_creates_stock_movement()
    {
        // Ajouter un ingrédient à un emplacement
        $initialQuantity = 0;
        $newQuantity = 10.5;

        // Utiliser updateExistingPivot ou attach selon le cas
        $this->ingredient->locations()->updateExistingPivot($this->location->id, ['quantity' => $newQuantity]);

        // S'assurer que le mouvement de stock a été enregistré
        $movement = StockMovement::where('trackable_id', $this->ingredient->id)
            ->where('trackable_type', get_class($this->ingredient))
            ->where('location_id', $this->location->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals('addition', $movement->type);
        $this->assertEquals($initialQuantity, $movement->quantity_before);
        $this->assertEquals($newQuantity, $movement->quantity_after);
        $this->assertEquals($newQuantity, $movement->quantity);
    }

    /**
     * Test que la modification de la quantité d'un ingrédient dans un emplacement
     * enregistre un mouvement de stock.
     */
    public function test_updating_ingredient_quantity_creates_stock_movement()
    {
        // D'abord, ajouter l'ingrédient avec une quantité initiale
        $initialQuantity = 5.0;
        $this->ingredient->locations()->updateExistingPivot($this->location->id, ['quantity' => $initialQuantity]);

        // Supprimer tout mouvement de stock généré par l'ajout initial
        StockMovement::where('trackable_id', $this->ingredient->id)->delete();

        // Modifier la quantité
        $newQuantity = 8.5;
        $this->ingredient->locations()->updateExistingPivot($this->location->id, ['quantity' => $newQuantity]);

        // Vérifier que le mouvement a été enregistré
        $movement = StockMovement::where('trackable_id', $this->ingredient->id)
            ->where('trackable_type', get_class($this->ingredient))
            ->where('location_id', $this->location->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals('addition', $movement->type);
        $this->assertEquals($initialQuantity, $movement->quantity_before);
        $this->assertEquals($newQuantity, $movement->quantity_after);
        $this->assertEquals(3.5, $movement->quantity);
    }

    /**
     * Test que la diminution de la quantité d'un ingrédient dans un emplacement
     * enregistre un mouvement de stock de type retrait.
     */
    public function test_reducing_ingredient_quantity_creates_withdrawal_stock_movement()
    {
        // D'abord, ajouter l'ingrédient avec une quantité initiale
        $initialQuantity = 15.0;
        $this->ingredient->locations()->updateExistingPivot($this->location->id, ['quantity' => $initialQuantity]);

        // Supprimer tout mouvement de stock généré par l'ajout initial
        StockMovement::where('trackable_id', $this->ingredient->id)->delete();

        // Réduire la quantité
        $newQuantity = 7.25;
        $this->ingredient->locations()->updateExistingPivot($this->location->id, ['quantity' => $newQuantity]);

        // Vérifier que le mouvement a été enregistré
        $movement = StockMovement::where('trackable_id', $this->ingredient->id)
            ->where('location_id', $this->location->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals('withdrawal', $movement->type);
        $this->assertEquals($initialQuantity, $movement->quantity_before);
        $this->assertEquals($newQuantity, $movement->quantity_after);
        $this->assertEquals(7.75, $movement->quantity);
    }

    /**
     * Test que l'ajout d'une préparation à un emplacement enregistre également
     * un mouvement de stock.
     */
    public function test_adding_preparation_to_location_creates_stock_movement()
    {
        // Ajouter une préparation à un emplacement
        $quantity = 3.0;
        $this->preparation->locations()->updateExistingPivot($this->location->id, ['quantity' => $quantity]);

        // Vérifier le mouvement de stock
        $movement = StockMovement::where('trackable_id', $this->preparation->id)
            ->where('trackable_type', get_class($this->preparation))
            ->where('location_id', $this->location->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals('addition', $movement->type);
        $this->assertEquals(0, $movement->quantity_before);
        $this->assertEquals($quantity, $movement->quantity_after);
    }

    /**
     * Test que la suppression complète d'un ingrédient d'un emplacement
     * enregistre un mouvement de retrait.
     */
    public function test_removing_ingredient_from_location_creates_withdrawal_movement()
    {
        // Ajouter d'abord l'ingrédient
        $initialQuantity = 12.5;
        $this->ingredient->locations()->updateExistingPivot($this->location->id, ['quantity' => $initialQuantity]);

        // Supprimer tout mouvement de stock généré par l'ajout initial
        StockMovement::where('trackable_id', $this->ingredient->id)->delete();

        // Supprimer l'ingrédient de l'emplacement
        $this->ingredient->locations()->detach($this->location->id);

        // Vérifier le mouvement de stock
        $movement = StockMovement::where('trackable_id', $this->ingredient->id)
            ->where('location_id', $this->location->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals('withdrawal', $movement->type);
        $this->assertEquals($initialQuantity, $movement->quantity_before);
        $this->assertEquals(0, $movement->quantity_after);
        $this->assertEquals($initialQuantity, $movement->quantity);
    }

    /**
     * Test que la modification de la quantité d'une préparation dans un emplacement
     * enregistre un mouvement de stock.
     */
    public function test_updating_preparation_quantity_creates_stock_movement()
    {
        // D'abord, ajouter la préparation avec une quantité initiale
        $initialQuantity = 4.0;
        $this->preparation->locations()->updateExistingPivot($this->location->id, ['quantity' => $initialQuantity]);

        // Supprimer tout mouvement de stock généré par l'ajout initial
        StockMovement::where('trackable_id', $this->preparation->id)->delete();

        // Modifier la quantité
        $newQuantity = 6.5;
        $this->preparation->locations()->updateExistingPivot($this->location->id, ['quantity' => $newQuantity]);

        // Vérifier que le mouvement a été enregistré
        $movement = StockMovement::where('trackable_id', $this->preparation->id)
            ->where('trackable_type', get_class($this->preparation))
            ->where('location_id', $this->location->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals('addition', $movement->type);
        $this->assertEquals($initialQuantity, $movement->quantity_before);
        $this->assertEquals($newQuantity, $movement->quantity_after);
        $this->assertEquals(2.5, $movement->quantity);
    }

    /**
     * Test que la suppression complète d'une préparation d'un emplacement
     * enregistre un mouvement de retrait.
     */
    public function test_removing_preparation_from_location_creates_withdrawal_movement()
    {
        // Ajouter d'abord la préparation
        $initialQuantity = 7.5;
        $this->preparation->locations()->updateExistingPivot($this->location->id, ['quantity' => $initialQuantity]);

        // Supprimer tout mouvement de stock généré par l'ajout initial
        StockMovement::where('trackable_id', $this->preparation->id)->delete();

        // Supprimer la préparation de l'emplacement
        $this->preparation->locations()->detach($this->location->id);

        // Vérifier le mouvement de stock
        $movement = StockMovement::where('trackable_id', $this->preparation->id)
            ->where('location_id', $this->location->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals('withdrawal', $movement->type);
        $this->assertEquals($initialQuantity, $movement->quantity_before);
        $this->assertEquals(0, $movement->quantity_after);
        $this->assertEquals($initialQuantity, $movement->quantity);
    }
}
