<?php

namespace Database\Seeders;

use App\Enums\PreparationTypeEnum;
use App\Models\Company;
use App\Models\Preparation;
use Illuminate\Database\Seeder;

class PreparationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::where('name', 'GoofyTeam')->first();

        Preparation::factory()
            ->count(40)
            ->sequence(
                ...array_map(
                    fn (string $type) => ['type' => $type],
                    PreparationTypeEnum::values()
                )
            )
            ->create([
                'company_id' => $company->id,
            ]);

        $otherCompanies = Company::where('name', '!=', 'GoofyTeam')->get();
        foreach ($otherCompanies as $company) {
            Preparation::factory()
                ->count(5)
                ->sequence(
                    ...array_map(
                        fn (string $type) => ['type' => $type],
                        PreparationTypeEnum::values()
                    )
                )
                ->create([
                    'company_id' => $company->id,
                ]);
        }
    }
}
