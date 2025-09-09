<?php

namespace Tests\Feature;

use App\Enums\Allergen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllergenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_all_allergens(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/allergens');

        $response->assertOk()->assertExactJson(Allergen::values());
    }
}
