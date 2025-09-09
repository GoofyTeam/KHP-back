<?php

namespace Tests\Feature;

use App\Enums\Allergen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class AllergenQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_it_lists_all_allergens(): void
    {
        $user = User::factory()->create();

        $query = /* @lang GraphQL */ '
            query {
                allergens
            }
        ';

        $response = $this->actingAs($user)->graphQL($query);

        $response->assertJson([
            'data' => [
                'allergens' => Allergen::values(),
            ],
        ]);
    }
}
