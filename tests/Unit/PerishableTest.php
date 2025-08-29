<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\Perishable;
use App\Models\Loss;
use App\Models\User;
use App\Services\PerishableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerishableTest extends TestCase
{
    use RefreshDatabase;

    public function test_remove_quantity_skips_expired_and_prioritizes_earliest_expiration(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $this->actingAs($user);

        $category = Category::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
        ]);
        $locationType = LocationType::factory()->create();
        $location = Location::factory()->create([
            'company_id' => $company->id,
            'location_type_id' => $locationType->id,
        ]);

        $category->locationTypes()->attach($locationType->id, ['shelf_life_hours' => 24]);

        $p1 = Perishable::create([
            'ingredient_id' => $ingredient->id,
            'location_id' => $location->id,
            'company_id' => $company->id,
            'quantity' => 2,
        ]);
        $p1->forceFill(['created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)])->save();

        $p2 = Perishable::create([
            'ingredient_id' => $ingredient->id,
            'location_id' => $location->id,
            'company_id' => $company->id,
            'quantity' => 2,
        ]);
        $p2->forceFill(['created_at' => now()->subHours(10), 'updated_at' => now()->subHours(10)])->save();

        Perishable::create([
            'ingredient_id' => $ingredient->id,
            'location_id' => $location->id,
            'company_id' => $company->id,
            'quantity' => 2,
        ]);

        $service = new PerishableService();
        $service->remove($ingredient->id, $location->id, $company->id, 3);

        $this->assertSame(2, Perishable::count());
        $this->assertDatabaseHas('perishables', [
            'ingredient_id' => $ingredient->id,
            'location_id' => $location->id,
            'company_id' => $company->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('perishables', [
            'ingredient_id' => $ingredient->id,
            'location_id' => $location->id,
            'company_id' => $company->id,
            'quantity' => 1,
        ]);
    }

    public function test_expire_command_creates_loss_and_soft_deletes(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
        ]);
        $locationType = LocationType::factory()->create();
        $location = Location::factory()->create([
            'company_id' => $company->id,
            'location_type_id' => $locationType->id,
        ]);

        $category->locationTypes()->attach($locationType->id, ['shelf_life_hours' => 1]);

        $perishable = Perishable::create([
            'ingredient_id' => $ingredient->id,
            'location_id' => $location->id,
            'company_id' => $company->id,
            'quantity' => 4,
        ]);
        $perishable->forceFill(['created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2)])->save();

        $this->artisan('perishables:expire')->assertExitCode(0);

        $this->assertSoftDeleted('perishables', ['id' => $perishable->id]);
        $this->assertDatabaseHas('losses', [
            'lossable_id' => $ingredient->id,
            'lossable_type' => Ingredient::class,
            'location_id' => $location->id,
            'company_id' => $company->id,
            'quantity' => 4,
            'reason' => 'expired',
        ]);
    }
}

