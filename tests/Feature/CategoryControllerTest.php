<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\LocationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
    }

    public function test_store_requires_fridge_and_freezer_shelf_lives(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/categories', [
            'name' => 'Fruits',
            'shelf_lives' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shelf_lives.fridge', 'shelf_lives.freezer']);
    }

    public function test_store_creates_category_with_shelf_lives(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/categories', [
            'name' => 'Viandes',
            'shelf_lives' => [
                'fridge' => 24,
                'freezer' => 72,
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Viandes');

        $category = Category::where('name', 'Viandes')->first();
        $this->assertNotNull($category);

        $fridge = LocationType::where('company_id', $this->company->id)->where('name', 'Réfrigérateur')->first();
        $freezer = LocationType::where('company_id', $this->company->id)->where('name', 'Congélateur')->first();

        $this->assertDatabaseHas('category_location_type', [
            'category_id' => $category->id,
            'location_type_id' => $fridge->id,
            'shelf_life_hours' => 24,
        ]);
        $this->assertDatabaseHas('category_location_type', [
            'category_id' => $category->id,
            'location_type_id' => $freezer->id,
            'shelf_life_hours' => 72,
        ]);
    }

    public function test_update_allows_partial_updates_and_keeps_fridge_and_freezer(): void
    {
        $this->actingAs($this->user);
        $category = Category::factory()->create(['company_id' => $this->company->id]);

        $extraType = LocationType::factory()->create(['company_id' => $this->company->id]);

        $response = $this->putJson('/api/categories/'.$category->id, [
            'shelf_lives' => [
                $extraType->id => 20,
            ],
        ]);

        $response->assertStatus(200);

        $fridge = LocationType::where('company_id', $this->company->id)->where('name', 'Réfrigérateur')->first();
        $freezer = LocationType::where('company_id', $this->company->id)->where('name', 'Congélateur')->first();

        $this->assertDatabaseHas('category_location_type', [
            'category_id' => $category->id,
            'location_type_id' => $fridge->id,
            'shelf_life_hours' => 24,
        ]);
        $this->assertDatabaseHas('category_location_type', [
            'category_id' => $category->id,
            'location_type_id' => $freezer->id,
            'shelf_life_hours' => 168,
        ]);
        $this->assertDatabaseHas('category_location_type', [
            'category_id' => $category->id,
            'location_type_id' => $extraType->id,
            'shelf_life_hours' => 20,
        ]);
    }

    public function test_update_rejects_null_for_fridge_and_freezer(): void
    {
        $this->actingAs($this->user);

        $category = Category::factory()->create(['company_id' => $this->company->id]);

        $response = $this->putJson('/api/categories/'.$category->id, [
            'shelf_lives' => [
                'fridge' => null,
                'freezer' => null,
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shelf_lives.fridge', 'shelf_lives.freezer']);
    }

    public function test_update_modifies_shelf_lives_and_manages_optional_location_types(): void
    {
        $this->actingAs($this->user);

        $category = Category::factory()->create(['company_id' => $this->company->id]);

        $oldType = LocationType::factory()->create(['company_id' => $this->company->id]);
        $category->locationTypes()->attach($oldType->id, ['shelf_life_hours' => 10]);

        $newType = LocationType::factory()->create(['company_id' => $this->company->id]);

        $response = $this->putJson('/api/categories/'.$category->id, [
            'shelf_lives' => [
                'fridge' => 48,
                'freezer' => 96,
                $newType->id => 20,
                $oldType->id => null,
            ],
        ]);

        $response->assertStatus(200);

        $fridge = LocationType::where('company_id', $this->company->id)->where('name', 'Réfrigérateur')->first();
        $freezer = LocationType::where('company_id', $this->company->id)->where('name', 'Congélateur')->first();

        $this->assertDatabaseHas('category_location_type', [
            'category_id' => $category->id,
            'location_type_id' => $fridge->id,
            'shelf_life_hours' => 48,
        ]);
        $this->assertDatabaseHas('category_location_type', [
            'category_id' => $category->id,
            'location_type_id' => $freezer->id,
            'shelf_life_hours' => 96,
        ]);
        $this->assertDatabaseHas('category_location_type', [
            'category_id' => $category->id,
            'location_type_id' => $newType->id,
            'shelf_life_hours' => 20,
        ]);
        $this->assertDatabaseMissing('category_location_type', [
            'category_id' => $category->id,
            'location_type_id' => $oldType->id,
        ]);
    }

    public function test_destroy_deletes_category_and_shelf_lives(): void
    {
        $this->actingAs($this->user);

        $category = Category::factory()->create(['company_id' => $this->company->id]);

        $fridge = LocationType::where('company_id', $this->company->id)->where('name', 'Réfrigérateur')->first();

        $response = $this->deleteJson('/api/categories/'.$category->id);
        $response->assertStatus(200);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('category_location_type', [
            'category_id' => $category->id,
            'location_type_id' => $fridge->id,
        ]);
    }
}
