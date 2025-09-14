<?php

namespace Tests\Feature;

use App\Enums\MeasurementUnit;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuOrderConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_order_deducts_converted_quantity(): void
    {
        $user = User::factory()->create();
        $user->company->update(['auto_complete_menu_orders' => true]);

        $ingredient = Ingredient::factory()->for($user->company)->create([
            'unit' => MeasurementUnit::KILOGRAM,
        ]);
        $location = Location::factory()->for($user->company)->create();
        $ingredient->locations()->sync([$location->id => ['quantity' => 16.6]]);

        $menu = Menu::factory()->for($user->company)->create();
        MenuItem::create([
            'menu_id' => $menu->id,
            'entity_id' => $ingredient->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 17,
            'unit' => MeasurementUnit::GRAM->value,
        ]);

        $this->actingAs($user)
            ->postJson(route('menus.command.store', ['menu' => $menu->id]), ['quantity' => 1])
            ->assertStatus(201);

        $remaining = $ingredient->locations()->find($location->id)->pivot->quantity;
        $this->assertEqualsWithDelta(16.583, $remaining, 0.001);
    }
}
