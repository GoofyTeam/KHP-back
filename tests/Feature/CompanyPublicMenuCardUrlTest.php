<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyPublicMenuCardUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_unique_public_menu_card_url_for_companies_with_same_name(): void
    {
        $first = Company::factory()->create(['name' => 'Duplicate Name']);
        $second = Company::factory()->create(['name' => 'Duplicate.Name']);

        $this->assertNotSame($first->public_menu_card_url, $second->public_menu_card_url);
    }

    public function test_public_menu_card_url_is_unique(): void
    {
        $company = Company::factory()->create();

        try {
            Company::factory()->create([
                'public_menu_card_url' => $company->public_menu_card_url,
            ]);

            $this->fail('Expected unique constraint violation.');
        } catch (QueryException $exception) {
            $this->assertTrue(in_array($exception->getCode(), ['23000', '23505'], true));
        }
    }
}
