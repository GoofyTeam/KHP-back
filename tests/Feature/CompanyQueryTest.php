<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyBusinessHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class CompanyQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_it_fetches_company_profile_with_business_hours(): void
    {
        $company = Company::factory()->create([
            'name' => 'Bistro Demo',
            'open_food_facts_language' => 'en',
            'public_menu_card_url' => 'share-card-demo',
            'show_out_of_stock_menus_on_card' => true,
            'show_menu_images' => false,
            'logo_path' => 'companies/demo.png',
            'contact_name' => 'Chef Demo',
            'contact_email' => 'contact@demo.test',
            'contact_phone' => '+33 1 23 45 67 89',
            'address_line' => '10 rue de la Demo',
            'postal_code' => '75000',
            'city' => 'Paris',
            'country' => 'France',
        ]);

        $morning = CompanyBusinessHour::factory()->for($company)->create([
            'day_of_week' => 1,
            'opens_at' => '09:00',
            'closes_at' => '12:30',
            'is_overnight' => false,
            'sequence' => 1,
        ]);

        $evening = CompanyBusinessHour::factory()->for($company)->create([
            'day_of_week' => 1,
            'opens_at' => '18:00',
            'closes_at' => '01:00',
            'is_overnight' => true,
            'sequence' => 2,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $query = /** @lang GraphQL */ 'query ($id: ID!) {
            company(id: $id) {
                id
                name
                open_food_facts_language
                public_menu_settings {
                    public_menu_card_url
                    show_out_of_stock_menus_on_card
                    show_menu_images
                }
                logo_path
                contact_name
                contact_email
                contact_phone
                address_line
                postal_code
                city
                country
                businessHours {
                    day_of_week
                    opens_at
                    closes_at
                    is_overnight
                    sequence
                }
            }
        }';

        $response = $this->actingAs($user)->graphQL($query, ['id' => $company->id]);

        $response->assertJsonPath('data.company.id', (string) $company->id);
        $response->assertJsonPath('data.company.name', 'Bistro Demo');
        $response->assertJsonPath('data.company.open_food_facts_language', 'en');
        $response->assertJsonPath('data.company.public_menu_settings.public_menu_card_url', 'share-card-demo');
        $response->assertJsonPath('data.company.public_menu_settings.show_out_of_stock_menus_on_card', true);
        $response->assertJsonPath('data.company.public_menu_settings.show_menu_images', false);
        $response->assertJsonPath('data.company.logo_path', url('/api/image-proxy/'.$company->logo_path));
        $response->assertJsonPath('data.company.contact_name', 'Chef Demo');
        $response->assertJsonPath('data.company.contact_email', 'contact@demo.test');
        $response->assertJsonPath('data.company.contact_phone', '+33 1 23 45 67 89');
        $response->assertJsonPath('data.company.address_line', '10 rue de la Demo');
        $response->assertJsonPath('data.company.postal_code', '75000');
        $response->assertJsonPath('data.company.city', 'Paris');
        $response->assertJsonPath('data.company.country', 'France');

        $response->assertJsonPath('data.company.businessHours.0.day_of_week', $morning->day_of_week);
        $response->assertJsonPath('data.company.businessHours.0.opens_at', '09:00');
        $response->assertJsonPath('data.company.businessHours.0.closes_at', '12:30');
        $response->assertJsonPath('data.company.businessHours.0.is_overnight', false);
        $response->assertJsonPath('data.company.businessHours.0.sequence', 1);

        $response->assertJsonPath('data.company.businessHours.1.day_of_week', $evening->day_of_week);
        $response->assertJsonPath('data.company.businessHours.1.opens_at', '18:00');
        $response->assertJsonPath('data.company.businessHours.1.closes_at', '01:00');
        $response->assertJsonPath('data.company.businessHours.1.is_overnight', true);
        $response->assertJsonPath('data.company.businessHours.1.sequence', 2);
    }

    public function test_it_lists_companies_filtered_by_name_and_sorted(): void
    {
        $first = Company::factory()->create(['name' => 'Cafe Alpha']);
        Company::factory()->create(['name' => 'Cafe Beta']);
        Company::factory()->create(['name' => 'Boulangerie Gamma']);

        $user = User::factory()->create([
            'company_id' => $first->id,
        ]);

        $query = /** @lang GraphQL */ 'query ($name: String) {
            companies(name: $name, orderBy: [{column: "name", order: ASC}]) {
                data {
                    id
                    name
                }
            }
        }';

        $response = $this->actingAs($user)->graphQL($query, ['name' => 'Cafe%']);

        $response->assertJsonCount(2, 'data.companies.data');
        $response->assertJsonPath('data.companies.data.0.name', 'Cafe Alpha');
        $response->assertJsonPath('data.companies.data.1.name', 'Cafe Beta');
        $response->assertJsonMissing(['name' => 'Boulangerie Gamma']);
    }
}
