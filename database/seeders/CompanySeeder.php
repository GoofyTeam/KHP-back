<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = [
            Company::factory()->create(['name' => 'GoofyTeam']),
            Company::factory()->create(['name' => 'Charlie Kirk']),
        ];

        foreach (Company::factory()->count(8)->create() as $company) {
            $companies[] = $company;
        }

        foreach ($companies as $company) {
            $this->seedBusinessHours($company);
        }
    }

    private function seedBusinessHours(Company $company): void
    {
        $company->businessHours()->delete();

        $standardService = [
            ['opens_at' => '11:30:00', 'closes_at' => '14:30:00', 'is_overnight' => false],
            ['opens_at' => '18:30:00', 'closes_at' => '22:30:00', 'is_overnight' => false],
        ];

        $weekendService = [
            ['opens_at' => '11:30:00', 'closes_at' => '15:00:00', 'is_overnight' => false],
            ['opens_at' => '18:30:00', 'closes_at' => '02:00:00', 'is_overnight' => true],
        ];

        $records = [];

        foreach (range(1, 7) as $day) {
            $slots = in_array($day, [6, 7], true) ? $weekendService : $standardService;
            $sequence = 1;

            foreach ($slots as $slot) {
                $records[] = [
                    'day_of_week' => $day,
                    'opens_at' => $slot['opens_at'],
                    'closes_at' => $slot['closes_at'],
                    'is_overnight' => $slot['is_overnight'] || $slot['closes_at'] <= $slot['opens_at'],
                    'sequence' => $sequence++,
                ];
            }
        }

        if ($records === []) {
            return;
        }

        $company->businessHours()->createMany($records);
    }
}
