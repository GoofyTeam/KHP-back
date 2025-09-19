<?php

namespace Tests\Feature;

use App\Enums\MeasurementUnit;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantCardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_restaurant_card(): void
    {
        $company = Company::factory()->create();
        $slug = $company->refresh()->public_menu_card_url;
        $locationType = LocationType::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create([
            'company_id' => $company->id,
            'location_type_id' => $locationType->id,
        ]);

        $ingredient = Ingredient::factory()->create([
            'company_id' => $company->id,
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::UNIT,
            'base_quantity' => 1,
            'allergens' => ['gluten'],
        ]);
        $ingredient->locations()->attach($location->id, ['quantity' => 5]);

        $category = MenuCategory::factory()->create(['company_id' => $company->id]);

        $menuAvailable = Menu::factory()->create([
            'company_id' => $company->id,
            'is_a_la_carte' => true,
            'type' => 'plat',
            'price' => 15.5,
        ]);
        $menuAvailable->update(['image_url' => 'https://example.com/menu.jpg']);
        $menuAvailable->categories()->sync([$category->id]);

        MenuItem::create([
            'menu_id' => $menuAvailable->id,
            'entity_id' => $ingredient->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => MeasurementUnit::UNIT,
        ]);

        $lowStockIngredient = Ingredient::factory()->create([
            'company_id' => $company->id,
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::UNIT,
            'base_quantity' => 1,
            'allergens' => [],
        ]);
        $lowStockIngredient->locations()->attach($location->id, ['quantity' => 0.5]);

        $menuInsufficient = Menu::factory()->create([
            'company_id' => $company->id,
            'is_a_la_carte' => true,
        ]);

        MenuItem::create([
            'menu_id' => $menuInsufficient->id,
            'entity_id' => $lowStockIngredient->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => MeasurementUnit::UNIT,
        ]);

        Menu::factory()->create([
            'company_id' => $company->id,
            'is_a_la_carte' => false,
        ]);

        $response = $this->getJson("/api/restaurant-card/{$slug}");

        $response
            ->assertStatus(200)
            ->assertJsonPath('company.name', $company->name)
            ->assertJsonPath('company.public_menu_card_url', $slug)
            ->assertJsonCount(1, 'company.menus')
            ->assertJsonPath('company.menus.0.name', $menuAvailable->name)
            ->assertJsonPath('company.menus.0.type', $menuAvailable->type)
            ->assertJsonPath('company.menus.0.price', $menuAvailable->price)
            ->assertJsonPath('company.menus.0.categories.0.name', $category->name)
            ->assertJsonPath('company.menus.0.allergens.0', 'gluten')
            ->assertJsonPath('company.menus.0.has_sufficient_stock', true)
            ->assertJsonPath('company.menus.0.image_url', 'https://example.com/menu.jpg');
    }

    public function test_includes_out_of_stock_menus_when_company_option_enabled(): void
    {
        $company = Company::factory()->create(['show_out_of_stock_menus_on_card' => true]);
        $slug = $company->refresh()->public_menu_card_url;
        $locationType = LocationType::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create([
            'company_id' => $company->id,
            'location_type_id' => $locationType->id,
        ]);

        $ingredient = Ingredient::factory()->create([
            'company_id' => $company->id,
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::UNIT,
            'base_quantity' => 1,
            'allergens' => [],
        ]);
        $ingredient->locations()->attach($location->id, ['quantity' => 1]);

        $menuAvailable = Menu::factory()->create([
            'company_id' => $company->id,
            'is_a_la_carte' => true,
        ]);
        $menuAvailable->update(['image_url' => 'https://example.com/available.jpg']);

        MenuItem::create([
            'menu_id' => $menuAvailable->id,
            'entity_id' => $ingredient->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => MeasurementUnit::UNIT,
        ]);

        $menuInsufficient = Menu::factory()->create([
            'company_id' => $company->id,
            'is_a_la_carte' => true,
        ]);
        $menuInsufficient->update(['image_url' => 'https://example.com/unavailable.jpg']);

        MenuItem::create([
            'menu_id' => $menuInsufficient->id,
            'entity_id' => $ingredient->id,
            'entity_type' => Ingredient::class,
            'location_id' => $location->id,
            'quantity' => 2,
            'unit' => MeasurementUnit::UNIT,
        ]);

        $response = $this->getJson("/api/restaurant-card/{$slug}");

        $response
            ->assertStatus(200)
            ->assertJsonCount(2, 'company.menus')
            ->assertJsonPath('company.menus.0.has_sufficient_stock', true)
            ->assertJsonPath('company.menus.1.has_sufficient_stock', false)
            ->assertJsonPath('company.menus.0.image_url', 'https://example.com/available.jpg')
            ->assertJsonPath('company.menus.1.image_url', 'https://example.com/unavailable.jpg');

        $company->update(['show_menu_images' => false]);

        $this->getJson("/api/restaurant-card/{$slug}")
            ->assertStatus(200)
            ->assertJsonPath('company.menus.0.image_url', null)
            ->assertJsonPath('company.menus.1.image_url', null);
    }

    public function test_restaurant_card_not_found(): void
    {
        $this->getJson('/api/restaurant-card/unknown-card')->assertStatus(404);
    }

    public function test_public_menu_card_url_validation(): void
    {
        $this->getJson('/api/restaurant-card/!!!')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['public_menu_card_url']);
    }
}
