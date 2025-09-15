<?php

namespace Tests\Unit;

use App\Enums\Allergen;
use App\Enums\MeasurementUnit;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Preparation;
use App\Models\PreparationEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllergenPropagationTest extends TestCase
{
    use RefreshDatabase;

    public function test_allergens_propagate_through_preparations_and_menus(): void
    {
        $company = Company::factory()->create();
        $location = Location::factory()->create(['company_id' => $company->id]);

        $ingredientA = Ingredient::factory()->create([
            'company_id' => $company->id,
            'allergens' => [Allergen::GLUTEN->value],
        ]);
        $ingredientB = Ingredient::factory()->create([
            'company_id' => $company->id,
            'allergens' => [Allergen::MILK->value, Allergen::EGGS->value],
        ]);

        $preparation = Preparation::factory()->create(['company_id' => $company->id]);
        PreparationEntity::create([
            'preparation_id' => $preparation->id,
            'entity_id' => $ingredientA->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => MeasurementUnit::UNIT,
        ]);

        $menu = Menu::factory()->create(['company_id' => $company->id]);

        MenuItem::create([
            'menu_id' => $menu->id,
            'entity_id' => $preparation->id,
            'entity_type' => Preparation::class,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => MeasurementUnit::UNIT,
        ]);

        MenuItem::create([
            'menu_id' => $menu->id,
            'entity_id' => $ingredientB->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => MeasurementUnit::UNIT,
        ]);

        $this->assertEquals([Allergen::GLUTEN->value], $preparation->allergens);
        $this->assertEqualsCanonicalizing(
            [Allergen::GLUTEN->value, Allergen::MILK->value, Allergen::EGGS->value],
            $menu->allergens
        );
    }
}
