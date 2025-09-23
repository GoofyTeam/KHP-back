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
use App\Models\MenuType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RestaurantCardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_restaurant_card(): void
    {
        $company = Company::factory()->create([
            'contact_name' => 'Claire Bouchon',
            'contact_email' => 'contact@example.com',
            'contact_phone' => '+33 4 72 00 00 00',
            'address_line' => '12 Rue des Canuts',
            'postal_code' => '69004',
            'city' => 'Lyon',
            'country' => 'France',
            'logo_path' => 'seeders/company/logo.jpg',
        ]);
        $slug = $company->refresh()->public_menu_card_url;

        $company->businessHours()->create([
            'day_of_week' => 1,
            'opens_at' => '12:00',
            'closes_at' => '14:30',
            'sequence' => 1,
            'is_overnight' => false,
        ]);
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

        $menuType = MenuType::factory()->create([
            'company_id' => $company->id,
            'name' => 'Plat',
        ]);
        $menuType->publicOrder()->update(['position' => 2]);

        $menuAvailable = Menu::factory()->create([
            'company_id' => $company->id,
            'is_a_la_carte' => true,
            'menu_type_id' => $menuType->id,
            'public_priority' => 3,
            'price' => 15.5,
        ]);
        $menuAvailable->update(['image_url' => 'menus/menu.jpg']);
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
            'menu_type_id' => $menuType->id,
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
            ->assertJsonPath('company.menus.0.type', $menuType->name)
            ->assertJsonPath('company.menus.0.menu_type_index', $menuType->publicOrder->position)
            ->assertJsonPath('company.menus.0.priority', $menuAvailable->public_priority)
            ->assertJsonPath('company.menus.0.menu_type_id', $menuType->id)
            ->assertJsonPath('company.menus.0.price', $menuAvailable->price)
            ->assertJsonPath('company.menus.0.categories.0.name', $category->name)
            ->assertJsonPath('company.menus.0.allergens.0', 'gluten')
            ->assertJsonPath('company.menus.0.has_sufficient_stock', true)
            ->assertJsonPath('company.menus.0.image_url', url('/api/public/image-proxy/'.$slug.'/menus/menu.jpg'))
            ->assertJsonMissingPath('company.logo_path')
            ->assertJsonPath('company.logo_url', url('/api/public/image-proxy/'.$slug.'/seeders/company/logo.jpg'))
            ->assertJsonPath('company.contact.name', 'Claire Bouchon')
            ->assertJsonPath('company.contact.email', 'contact@example.com')
            ->assertJsonPath('company.contact.phone', '+33 4 72 00 00 00')
            ->assertJsonPath('company.address.line', '12 Rue des Canuts')
            ->assertJsonPath('company.address.postal_code', '69004')
            ->assertJsonPath('company.address.city', 'Lyon')
            ->assertJsonPath('company.address.country', 'France')
            ->assertJsonPath('company.settings.show_out_of_stock_menus_on_card', false)
            ->assertJsonPath('company.settings.show_menu_images', true)
            ->assertJsonCount(1, 'company.business_hours')
            ->assertJsonPath('company.business_hours.0.day_of_week', 1)
            ->assertJsonPath('company.business_hours.0.opens_at', '12:00')
            ->assertJsonPath('company.business_hours.0.closes_at', '14:30')
            ->assertJsonPath('company.business_hours.0.sequence', 1)
            ->assertJsonPath('company.business_hours.0.is_overnight', false);
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

        $menuType = MenuType::factory()->create([
            'company_id' => $company->id,
            'name' => 'Plat',
        ]);
        $menuType->publicOrder()->update(['position' => 1]);

        $menuAvailable = Menu::factory()->create([
            'company_id' => $company->id,
            'is_a_la_carte' => true,
            'menu_type_id' => $menuType->id,
            'public_priority' => 1,
        ]);
        $menuAvailable->update(['image_url' => 'menus/available.jpg']);

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
            'menu_type_id' => $menuType->id,
            'public_priority' => 2,
        ]);
        $menuInsufficient->update(['image_url' => 'menus/unavailable.jpg']);

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
            ->assertJsonPath('company.menus.0.image_url', url('/api/public/image-proxy/'.$slug.'/menus/available.jpg'))
            ->assertJsonPath('company.menus.1.image_url', url('/api/public/image-proxy/'.$slug.'/menus/unavailable.jpg'));

        $company->update(['show_menu_images' => false]);

        $this->getJson("/api/restaurant-card/{$slug}")
            ->assertStatus(200)
            ->assertJsonPath('company.menus.0.image_url', null)
            ->assertJsonPath('company.menus.1.image_url', null);
    }

    public function test_menus_are_sorted_by_type_priority_and_name(): void
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
        ]);
        $ingredient->locations()->attach($location->id, ['quantity' => 10]);

        $platType = MenuType::factory()->create([
            'company_id' => $company->id,
            'name' => 'Plat',
        ]);
        $platType->publicOrder()->update(['position' => 1]);

        $dessertType = MenuType::factory()->create([
            'company_id' => $company->id,
            'name' => 'Dessert',
        ]);
        $dessertType->publicOrder()->update(['position' => 2]);

        $menuTypeOneFirst = Menu::factory()->create([
            'company_id' => $company->id,
            'is_a_la_carte' => true,
            'menu_type_id' => $platType->id,
            'public_priority' => 1,
            'name' => 'Alpha Plat',
        ]);

        $menuTypeOneSecond = Menu::factory()->create([
            'company_id' => $company->id,
            'is_a_la_carte' => true,
            'menu_type_id' => $platType->id,
            'public_priority' => 1,
            'name' => 'Bravo Plat',
        ]);

        $menuTypeOneLater = Menu::factory()->create([
            'company_id' => $company->id,
            'is_a_la_carte' => true,
            'menu_type_id' => $platType->id,
            'public_priority' => 5,
            'name' => 'Charlie Plat',
        ]);

        $menuTypeTwo = Menu::factory()->create([
            'company_id' => $company->id,
            'is_a_la_carte' => true,
            'menu_type_id' => $dessertType->id,
            'public_priority' => 1,
            'name' => 'Dessert Gourmand',
        ]);

        foreach ([$menuTypeOneFirst, $menuTypeOneSecond, $menuTypeOneLater, $menuTypeTwo] as $menu) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'entity_id' => $ingredient->id,
                'entity_type' => Ingredient::class,
                'location_id' => $location->id,
                'quantity' => 1,
                'unit' => MeasurementUnit::UNIT,
            ]);
        }

        $response = $this->getJson("/api/restaurant-card/{$slug}");

        $response
            ->assertStatus(200)
            ->assertJsonCount(4, 'company.menus')
            ->assertJsonPath('company.menus.0.id', $menuTypeOneFirst->id)
            ->assertJsonPath('company.menus.1.id', $menuTypeOneSecond->id)
            ->assertJsonPath('company.menus.2.id', $menuTypeOneLater->id)
            ->assertJsonPath('company.menus.3.id', $menuTypeTwo->id);
    }

    public function test_public_image_proxy_serves_menu_images(): void
    {
        Storage::fake('s3');

        $company = Company::factory()->create();
        $slug = $company->refresh()->public_menu_card_url;

        Menu::factory()->create([
            'company_id' => $company->id,
            'is_a_la_carte' => true,
            'image_url' => 'menus/sample.jpg',
        ]);

        $path = 'menus/sample.jpg';
        Storage::disk('s3')->put($path, 'fake-image');
        $fullPath = Storage::disk('s3')->path($path);

        $disk = \Mockery::mock(Storage::disk('s3'))->makePartial();
        $disk->shouldReceive('exists')->with($path)->andReturnTrue();
        $disk->shouldReceive('temporaryUrl')
            ->with($path, \Mockery::type('DateTimeInterface'))
            ->andReturn($fullPath);
        $disk->shouldReceive('mimeType')->with($path)->andReturn('image/jpeg');

        Storage::shouldReceive('disk')->with('s3')->andReturn($disk);

        $response = $this->get("/api/public/image-proxy/{$slug}/menus/sample.jpg")
            ->assertStatus(200);

        $this->assertSame('fake-image', $response->getContent());
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
    }

    public function test_public_image_proxy_serves_company_logo(): void
    {
        Storage::fake('s3');

        $company = Company::factory()->create([
            'logo_path' => 'seeders/company/logo.jpg',
        ]);
        $slug = $company->refresh()->public_menu_card_url;

        $path = 'seeders/company/logo.jpg';
        Storage::disk('s3')->put($path, 'fake-logo');
        $fullPath = Storage::disk('s3')->path($path);

        $disk = \Mockery::mock(Storage::disk('s3'))->makePartial();
        $disk->shouldReceive('exists')->with($path)->andReturnTrue();
        $disk->shouldReceive('temporaryUrl')
            ->with($path, \Mockery::type('DateTimeInterface'))
            ->andReturn($fullPath);
        $disk->shouldReceive('mimeType')->with($path)->andReturn('image/png');

        Storage::shouldReceive('disk')->with('s3')->andReturn($disk);

        $response = $this->get("/api/public/image-proxy/{$slug}/seeders/company/logo.jpg")
            ->assertStatus(200);

        $this->assertSame('fake-logo', $response->getContent());
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
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
