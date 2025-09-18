<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Location;
use App\Models\Preparation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class PreparationThresholdQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_preparations_below_threshold_query_returns_understocked_preparations(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);

        $below = Preparation::factory()->create([
            'company_id' => $company->id,
            'threshold' => 10,
        ]);
        $below->locations()->sync([$location->id => ['quantity' => 5]]);

        $above = Preparation::factory()->create([
            'company_id' => $company->id,
            'threshold' => 5,
        ]);
        $above->locations()->sync([$location->id => ['quantity' => 7]]);

        $noThreshold = Preparation::factory()->create([
            'company_id' => $company->id,
            'threshold' => null,
        ]);
        $noThreshold->locations()->sync([$location->id => ['quantity' => 1]]);

        $response = $this->actingAs($user)->graphQL(/** @lang GraphQL */ '
            {
                preparationsBelowThreshold {
                    id
                    threshold
                }
            }
        ');

        $response->assertJsonCount(1, 'data.preparationsBelowThreshold');

        $ids = collect($response->json('data.preparationsBelowThreshold'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame([$below->id], $ids);
        $this->assertEquals(10.0, $response->json('data.preparationsBelowThreshold.0.threshold'));
    }

    public function test_preparations_below_threshold_query_can_filter_by_location(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $locationA = Location::factory()->create(['company_id' => $company->id]);
        $locationB = Location::factory()->create(['company_id' => $company->id]);

        $preparation = Preparation::factory()->create([
            'company_id' => $company->id,
            'threshold' => 10,
        ]);
        $preparation->locations()->sync([
            $locationA->id => ['quantity' => 5],
            $locationB->id => ['quantity' => 12],
        ]);

        $query = /** @lang GraphQL */ '
            query ($locationIds: [ID!]) {
                preparationsBelowThreshold(locationIds: $locationIds) {
                    id
                }
            }
        ';

        $responseForA = $this->actingAs($user)->graphQL($query, [
            'locationIds' => [$locationA->id],
        ]);
        $responseForA->assertJsonFragment(['id' => (string) $preparation->id]);

        $responseForB = $this->actingAs($user)->graphQL($query, [
            'locationIds' => [$locationB->id],
        ]);
        $responseForB->assertJsonMissing(['id' => (string) $preparation->id]);
    }
}
