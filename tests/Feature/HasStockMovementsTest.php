<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Preparation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cette suite de tests vérifie le fonctionnement du trait HasStockMovements
 * qui permet de tracer et d'enregistrer les mouvements de stock pour les ingrédients et préparations.
 */
class HasStockMovementsTest extends TestCase
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
     * Ce test vérifie que le trait enregistre correctement un mouvement d'ajout de stock
     * avec toutes les informations nécessaires.
     */
    public function test_records_addition_stock_movement()
    {
        // Tester un ajout de stock
        $quantityBefore = 10.0;
        $quantityAfter = 15.0;

        $movement = $this->ingredient->recordStockMovement(
            $this->location,
            $quantityBefore,
            $quantityAfter,
            'Inventaire'
        );

        // Assertions
        $this->assertNotNull($movement);
        $this->assertEquals('addition', $movement->type);
        $this->assertEquals(5.0, $movement->quantity);
        $this->assertEquals($quantityBefore, $movement->quantity_before);
        $this->assertEquals($quantityAfter, $movement->quantity_after);
        $this->assertEquals($this->location->id, $movement->location_id);
        $this->assertEquals($this->user->id, $movement->user_id);
        $this->assertEquals($this->company->id, $movement->company_id);
        $this->assertEquals('Inventaire', $movement->reason);
    }

    /**
     * Ce test vérifie que le trait enregistre correctement un mouvement de retrait de stock
     * avec le type approprié et les bonnes quantités.
     */
    public function test_records_withdrawal_stock_movement()
    {
        // Tester un retrait de stock
        $quantityBefore = 20.0;
        $quantityAfter = 15.5;

        $movement = $this->ingredient->recordStockMovement(
            $this->location,
            $quantityBefore,
            $quantityAfter,
            'Retrait'
        );

        // Assertions
        $this->assertNotNull($movement);
        $this->assertEquals('withdrawal', $movement->type);
        $this->assertEquals(4.5, $movement->quantity);
        $this->assertEquals($quantityBefore, $movement->quantity_before);
        $this->assertEquals($quantityAfter, $movement->quantity_after);
    }

    /**
     * Ce test vérifie que les quantités sont correctement arrondies à deux décimales
     * pour assurer la cohérence des données.
     */
    public function test_rounds_quantities_to_two_decimals()
    {
        $movement = $this->ingredient->recordStockMovement(
            $this->location,
            10.123,
            15.456,
            'Ajustement'
        );

        $this->assertEquals(10.12, $movement->quantity_before);
        $this->assertEquals(15.46, $movement->quantity_after);
        $this->assertEquals(5.34, $movement->quantity);
    }

    /**
     * Ce test vérifie que le trait n'enregistre pas de mouvement pour des changements
     * de quantité trop petits, évitant ainsi des entrées inutiles.
     */
    public function test_does_not_record_movement_for_very_small_changes()
    {
        // Tester un changement très petit
        $movement = $this->ingredient->recordStockMovement(
            $this->location,
            10.005,
            10.009,
            'Minuscule'
        );

        // Ne devrait pas créer de mouvement
        $this->assertNull($movement);
        $this->assertCount(0, $this->ingredient->stockMovements);
    }

    /**
     * Ce test vérifie que le trait fonctionne correctement avec différents modèles
     * qui l'utilisent, comme les préparations.
     */
    public function test_works_with_preparation_model_too()
    {
        // Tester le trait avec une préparation
        $movement = $this->preparation->recordStockMovement(
            $this->location,
            5.0,
            8.0,
            'Ajout'
        );

        $this->assertNotNull($movement);
        $this->assertEquals('addition', $movement->type);
        $this->assertEquals(3.0, $movement->quantity);
        $this->assertEquals($this->preparation->id, $movement->trackable_id);
        $this->assertEquals(get_class($this->preparation), $movement->trackable_type);
    }
}
