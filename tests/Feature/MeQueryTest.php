<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class MeQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_it_returns_authenticated_user_with_company(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->graphQL(/** @lang GraphQL */ '
            query {
                me {
                    id
                    name
                    email
                    company { id name }
                }
            }
        ');

        $response->assertJson([
            'data' => [
                'me' => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'company' => [
                        'id' => (string) $user->company->id,
                        'name' => $user->company->name,
                    ],
                ],
            ],
        ]);
    }
}
