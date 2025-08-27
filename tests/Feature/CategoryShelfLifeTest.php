<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryShelfLifeTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_has_shelf_life_for_fridge_and_freezer(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);

        $locationTypes = $category->locationTypes()->pluck('shelf_life_hours', 'name');

        $this->assertArrayHasKey('Réfrigérateur', $locationTypes);
        $this->assertArrayHasKey('Congélateur', $locationTypes);
        $this->assertSame(24, $locationTypes['Réfrigérateur']);
        $this->assertSame(168, $locationTypes['Congélateur']);
    }
}
