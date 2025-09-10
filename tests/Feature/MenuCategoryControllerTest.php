<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\MenuCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_store_creates_menu_category(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/menu-categories', ['name' => 'Halal']);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Halal');

        $this->assertDatabaseHas('menu_categories', [
            'name' => 'Halal',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_update_modifies_menu_category(): void
    {
        $category = MenuCategory::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->putJson('/api/menu-categories/'.$category->id, ['name' => 'Vegan']);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Vegan');

        $this->assertDatabaseHas('menu_categories', [
            'id' => $category->id,
            'name' => 'Vegan',
        ]);
    }

    public function test_destroy_deletes_menu_category(): void
    {
        $category = MenuCategory::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson('/api/menu-categories/'.$category->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('menu_categories', ['id' => $category->id]);
    }
}
