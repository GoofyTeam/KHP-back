<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyBusinessHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Class CompanyControllerTest
 *
 * Use cases couverts :
 * - Mettre à jour la langue Open Food Facts de l'entreprise
 */
class CompanyControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Vérifie que la langue d'Open Food Facts peut être modifiée. */
    public function test_update_open_food_facts_language(): void
    {
        $company = Company::factory()->create(['open_food_facts_language' => 'fr']);
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)
            ->putJson('/api/company/options', [
                'open_food_facts_language' => 'en',
            ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.open_food_facts_language', 'en')
            ->assertJsonPath('data.business_hours', []);

        $company->refresh();

        $this->assertSame('en', $company->open_food_facts_language);
        $this->assertSame(
            sprintf('%d-%s', $company->id, Str::slug($company->name)),
            $company->public_menu_card_url
        );
        $this->assertFalse($company->show_out_of_stock_menus_on_card);
        $this->assertTrue($company->show_menu_images);
    }

    public function test_update_contact_information(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $payload = [
            'contact_name' => 'Chef Gaston',
            'contact_email' => 'contact@example.com',
            'contact_phone' => '+33 1 23 45 67 89',
            'address_line' => '12 rue des Oliviers',
            'postal_code' => '75001',
            'city' => 'Paris',
            'country' => 'France',
        ];

        $response = $this->actingAs($user)
            ->putJson('/api/company/contact', $payload)
            ->assertStatus(200);

        foreach ($payload as $key => $value) {
            $response->assertJsonPath("data.{$key}", $value);
        }

        $company->refresh();

        foreach ($payload as $key => $value) {
            $this->assertSame($value, $company->getAttribute($key));
        }
    }

    public function test_update_contact_information_can_clear_fields_and_normalizes_email(): void
    {
        $company = Company::factory()->create([
            'contact_name' => 'Chef Initial',
            'contact_email' => 'initial@example.com',
            'contact_phone' => '+33 1 98 76 54 32',
            'address_line' => '10 rue de la Paix',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)
            ->putJson('/api/company/contact', [
                'contact_name' => '  Chef Marie  ',
                'contact_email' => 'CONTACT@EXAMPLE.COM ',
                'contact_phone' => '   ',
                'address_line' => '',
            ])
            ->assertStatus(200);

        $response->assertJsonPath('data.contact_name', 'Chef Marie');
        $response->assertJsonPath('data.contact_email', 'contact@example.com');
        $response->assertJsonPath('data.contact_phone', null);
        $response->assertJsonPath('data.address_line', null);

        $company->refresh();

        $this->assertSame('Chef Marie', $company->contact_name);
        $this->assertSame('contact@example.com', $company->contact_email);
        $this->assertNull($company->contact_phone);
        $this->assertNull($company->address_line);
    }

    public function test_update_business_hours(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $payload = [
            'business_hours' => [
                ['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '12:00'],
                ['day_of_week' => 1, 'opens_at' => '18:00', 'closes_at' => '02:00', 'is_overnight' => true],
                ['day_of_week' => 6, 'opens_at' => '10:30', 'closes_at' => '15:00'],
            ],
        ];

        $response = $this->actingAs($user)
            ->putJson('/api/company/business-hours', $payload)
            ->assertStatus(200)
            ->json('data.business_hours');

        $this->assertCount(3, $response);
        $this->assertSame([1, 1, 6], Arr::pluck($response, 'day_of_week'));
        $this->assertSame(['09:00', '18:00', '10:30'], Arr::pluck($response, 'opens_at'));
        $this->assertSame([false, true, false], Arr::pluck($response, 'is_overnight'));

        $hours = CompanyBusinessHour::query()
            ->where('company_id', $company->id)
            ->orderBy('day_of_week')
            ->orderBy('sequence')
            ->get();

        $this->assertCount(3, $hours);
        $this->assertSame('09:00:00', $hours[0]->opens_at);
        $this->assertSame('12:00:00', $hours[0]->closes_at);
        $this->assertFalse($hours[0]->is_overnight);
        $this->assertSame(1, $hours[0]->sequence);

        $this->assertSame('18:00:00', $hours[1]->opens_at);
        $this->assertSame('02:00:00', $hours[1]->closes_at);
        $this->assertTrue($hours[1]->is_overnight);
        $this->assertSame(2, $hours[1]->sequence);
    }

    public function test_update_business_hours_supports_day_name_payload(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $payload = [
            'business_hours' => [
                'monday' => [
                    ['opens_at' => '08:00', 'closes_at' => '12:00'],
                ],
                'dimanche' => [
                    ['opens_at' => '18:00', 'closes_at' => '01:00', 'is_overnight' => true],
                ],
            ],
        ];

        $this->actingAs($user)
            ->putJson('/api/company/business-hours', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.business_hours.0.day_of_week', 1)
            ->assertJsonPath('data.business_hours.1.day_of_week', 7);

        $hours = CompanyBusinessHour::query()
            ->where('company_id', $company->id)
            ->orderBy('day_of_week')
            ->get();

        $this->assertCount(2, $hours);
        $this->assertSame(1, $hours[0]->day_of_week);
        $this->assertSame(7, $hours[1]->day_of_week);
    }

    public function test_update_business_hours_orders_slots_and_resets_sequence_per_day(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $payload = [
            'business_hours' => [
                ['day_of_week' => 4, 'opens_at' => '18:30', 'closes_at' => '23:00'],
                ['day_of_week' => 4, 'opens_at' => '11:30', 'closes_at' => '14:30'],
                ['day_of_week' => 5, 'opens_at' => '09:00', 'closes_at' => '12:00'],
            ],
        ];

        $response = $this->actingAs($user)
            ->putJson('/api/company/business-hours', $payload)
            ->assertStatus(200)
            ->json('data.business_hours');

        $this->assertSame([4, 4, 5], Arr::pluck($response, 'day_of_week'));
        $this->assertSame(['11:30', '18:30', '09:00'], Arr::pluck($response, 'opens_at'));
        $this->assertSame([1, 2, 1], Arr::pluck($response, 'sequence'));

        $company->refresh();

        $grouped = $company->businessHours
            ->groupBy('day_of_week')
            ->map(fn ($collection) => $collection->values());

        $thursday = $grouped->get(4);
        $friday = $grouped->get(5);

        $this->assertNotNull($thursday);
        $this->assertNotNull($friday);

        $this->assertSame(['11:30:00', '18:30:00'], $thursday->pluck('opens_at')->all());
        $this->assertSame([1, 2], $thursday->pluck('sequence')->all());
        $this->assertSame(['09:00:00'], $friday->pluck('opens_at')->all());
        $this->assertSame([1], $friday->pluck('sequence')->all());
    }

    public function test_update_business_hours_replaces_previous_entries(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        CompanyBusinessHour::factory()->for($company)->create([
            'day_of_week' => 1,
            'opens_at' => '09:00',
            'closes_at' => '12:00',
            'sequence' => 1,
        ]);

        CompanyBusinessHour::factory()->for($company)->create([
            'day_of_week' => 2,
            'opens_at' => '14:00',
            'closes_at' => '18:00',
            'sequence' => 1,
        ]);

        $payload = [
            'business_hours' => [
                ['day_of_week' => 3, 'opens_at' => '10:00', 'closes_at' => '16:00'],
            ],
        ];

        $response = $this->actingAs($user)
            ->putJson('/api/company/business-hours', $payload)
            ->assertStatus(200)
            ->json('data.business_hours');

        $this->assertCount(1, $response);
        $this->assertSame(3, $response[0]['day_of_week']);
        $this->assertSame('10:00', $response[0]['opens_at']);

        $this->assertDatabaseCount('company_business_hours', 1);

        $hour = CompanyBusinessHour::query()->first();
        $this->assertNotNull($hour);
        $this->assertSame(3, $hour->day_of_week);
        $this->assertSame('10:00:00', $hour->opens_at);
        $this->assertSame('16:00:00', $hour->closes_at);
        $this->assertSame(1, $hour->sequence);
    }

    public function test_update_business_hours_clears_existing_entries_with_empty_payload(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        CompanyBusinessHour::factory()->for($company)->create([
            'day_of_week' => 6,
            'opens_at' => '19:00',
            'closes_at' => '23:00',
        ]);

        $this->actingAs($user)
            ->putJson('/api/company/business-hours', ['business_hours' => []])
            ->assertStatus(200)
            ->assertJsonPath('data.business_hours', []);

        $this->assertDatabaseCount('company_business_hours', 0);
    }

    public function test_update_business_hours_rejects_overlapping_intervals(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->putJson('/api/company/business-hours', [
                'business_hours' => [
                    ['day_of_week' => 2, 'opens_at' => '09:00', 'closes_at' => '12:00'],
                    ['day_of_week' => 2, 'opens_at' => '11:00', 'closes_at' => '14:00'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['business_hours.1.opens_at']);
    }

    public function test_update_business_hours_rejects_wraparound_overlap(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->putJson('/api/company/business-hours', [
                'business_hours' => [
                    ['day_of_week' => 7, 'opens_at' => '18:00', 'closes_at' => '02:00', 'is_overnight' => true],
                    ['day_of_week' => 1, 'opens_at' => '01:00', 'closes_at' => '05:00'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['business_hours.1.opens_at']);
    }

    public function test_update_business_hours_rejects_close_before_open_without_overnight_flag(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->putJson('/api/company/business-hours', [
                'business_hours' => [
                    ['day_of_week' => 5, 'opens_at' => '18:00', 'closes_at' => '02:00'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['business_hours.0.closes_at']);
    }

    public function test_update_business_hours_rejects_identical_open_and_close_times(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->putJson('/api/company/business-hours', [
                'business_hours' => [
                    ['day_of_week' => 3, 'opens_at' => '10:00', 'closes_at' => '10:00'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['business_hours.0.closes_at']);
    }

    public function test_update_business_hours_rejects_invalid_day_key(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->putJson('/api/company/business-hours', [
                'business_hours' => [
                    'funday' => [
                        ['opens_at' => '09:00', 'closes_at' => '17:00'],
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['business_hours']);
    }

    public function test_update_business_hours_rejects_invalid_time(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->putJson('/api/company/business-hours', [
                'business_hours' => [
                    ['day_of_week' => 3, 'opens_at' => 'invalid', 'closes_at' => '17:00'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['business_hours.0.opens_at']);
    }

    public function test_upload_company_logo_requires_file_or_url(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->postJson('/api/company/logo', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image', 'image_url']);
    }

    public function test_upload_company_logo_with_file(): void
    {
        Storage::fake('s3');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $file = UploadedFile::fake()->image('logo.jpg', 300, 300);

        $response = $this->actingAs($user)
            ->postJson('/api/company/logo', [
                'image' => $file,
            ])
            ->assertStatus(200)
            ->json('data.logo_path');

        $this->assertNotNull($response);
        $this->assertStringStartsWith('companies/', $response);
        $this->assertTrue(Storage::disk('s3')->exists($response));

        $company->refresh();
        $this->assertSame($response, $company->logo_path);
    }

    public function test_upload_company_logo_with_url(): void
    {
        Storage::fake('s3');

        $imageBytes = random_bytes(1024);

        Http::fake([
            'example.com/*' => Http::response($imageBytes, 200, [
                'Content-Type' => 'image/png',
                'Content-Length' => strlen($imageBytes),
            ]),
        ]);

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/company/logo', [
                'image_url' => 'https://example.com/logo.png',
            ])
            ->assertStatus(200)
            ->json('data.logo_path');

        $this->assertNotNull($response);
        $this->assertTrue(Storage::disk('s3')->exists($response));

        $company->refresh();
        $this->assertSame($response, $company->logo_path);
    }

    public function test_upload_company_logo_replaces_previous_file(): void
    {
        Storage::fake('s3');

        $company = Company::factory()->create(['logo_path' => 'companies/old-logo.png']);
        Storage::disk('s3')->put('companies/old-logo.png', 'old');

        $user = User::factory()->create(['company_id' => $company->id]);

        $newFile = UploadedFile::fake()->image('new-logo.png');

        $newPath = $this->actingAs($user)
            ->postJson('/api/company/logo', [
                'image' => $newFile,
            ])
            ->assertStatus(200)
            ->json('data.logo_path');

        $this->assertNotNull($newPath);
        $this->assertTrue(Storage::disk('s3')->exists($newPath));
        $this->assertFalse(Storage::disk('s3')->exists('companies/old-logo.png'));

        $company->refresh();
        $this->assertSame($newPath, $company->logo_path);
    }
}
