<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class LossReasonQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_it_lists_company_loss_reasons(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->graphQL(/** @lang GraphQL */ '
            query {
                lossReasons { id name }
            }
        ');

        $response->assertJsonCount(7, 'data.lossReasons');
        $response->assertJsonFragment(['name' => 'Expired']);
    }
}
