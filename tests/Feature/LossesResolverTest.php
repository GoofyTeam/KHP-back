<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Loss;
use App\Models\Preparation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class LossesStatsResolverTest extends TestCase
{
    use RefreshDatabase;
    use MakesGraphQLRequests;

    protected Company $company;
    protected User $user;
    protected Location $location;
    protected Ingredient $ingredient;
    protected Preparation $preparation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->location = Location::factory()->create(['company_id' => $this->company->id]);
        $this->ingredient = Ingredient::factory()->create(['company_id' => $this->company->id]);
        $this->preparation = Preparation::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($this->user);
    }

    public function test_default_previous_week_range(): void
    {
        // Losses inside the default range
        Loss::factory()->create([
            'lossable_id' => $this->ingredient->id,
            'lossable_type' => Ingredient::class,
            'company_id' => $this->company->id,
            'location_id' => $this->location->id,
            'user_id' => $this->user->id,
            'quantity' => 2,
            'created_at' => now()->subDays(2),
        ]);

        Loss::factory()->create([
            'lossable_id' => $this->preparation->id,
            'lossable_type' => Preparation::class,
            'company_id' => $this->company->id,
            'location_id' => $this->location->id,
            'user_id' => $this->user->id,
            'quantity' => 1,
            'created_at' => now()->subDay(),
        ]);

        // Losses outside the default range
        Loss::factory()->create([
            'lossable_id' => $this->ingredient->id,
            'lossable_type' => Ingredient::class,
            'company_id' => $this->company->id,
            'location_id' => $this->location->id,
            'user_id' => $this->user->id,
            'quantity' => 3,
            'created_at' => now()->subDays(8),
        ]);

        Loss::factory()->create([
            'lossable_id' => $this->preparation->id,
            'lossable_type' => Preparation::class,
            'company_id' => $this->company->id,
            'location_id' => $this->location->id,
            'user_id' => $this->user->id,
            'quantity' => 4,
            'created_at' => now()->subDays(9),
        ]);

        $response = $this->graphQL(<<<'GRAPHQL'
        {
            lossesStats {
                ingredient
                preparation
                total
            }
        }
        GRAPHQL);

        $response->assertJson([
            'data' => [
                'lossesStats' => [
                    'ingredient' => 2.0,
                    'preparation' => 1.0,
                    'total' => 3.0,
                ],
            ],
        ]);
    }

    public function test_custom_date_range(): void
    {
        // Losses in the custom range
        Loss::factory()->create([
            'lossable_id' => $this->ingredient->id,
            'lossable_type' => Ingredient::class,
            'company_id' => $this->company->id,
            'location_id' => $this->location->id,
            'user_id' => $this->user->id,
            'quantity' => 3,
            'created_at' => now()->subDays(8),
        ]);

        Loss::factory()->create([
            'lossable_id' => $this->preparation->id,
            'lossable_type' => Preparation::class,
            'company_id' => $this->company->id,
            'location_id' => $this->location->id,
            'user_id' => $this->user->id,
            'quantity' => 4,
            'created_at' => now()->subDays(9),
        ]);

        // Loss outside custom range
        Loss::factory()->create([
            'lossable_id' => $this->ingredient->id,
            'lossable_type' => Ingredient::class,
            'company_id' => $this->company->id,
            'location_id' => $this->location->id,
            'user_id' => $this->user->id,
            'quantity' => 2,
            'created_at' => now()->subDay(),
        ]);

        $start = now()->subDays(10);
        $end = now()->subDays(7);

        $query = <<<'GRAPHQL'
        query($start: DateTime!, $end: DateTime!) {
            lossesStats(start_date: $start, end_date: $end) {
                ingredient
                preparation
                total
            }
        }
        GRAPHQL;

        $response = $this->graphQL($query, [
            'start' => $start->toIso8601String(),
            'end' => $end->toIso8601String(),
        ]);

        $response->assertJson([
            'data' => [
                'lossesStats' => [
                    'ingredient' => 3.0,
                    'preparation' => 4.0,
                    'total' => 7.0,
                ],
            ],
        ]);
    }
}
