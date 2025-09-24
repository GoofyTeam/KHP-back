<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\Concerns\FiltersSeedableCompanies;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    use FiltersSeedableCompanies;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $goofyCompany = Company::where('name', 'GoofyTeam')->first();

        if ($goofyCompany === null || $this->isExcludedCompanyId($goofyCompany->id)) {
            return;
        }

        $users = [
            ['name' => 'Luca',    'email' => 'luca@example.com'],
            ['name' => 'Adrien',  'email' => 'adrien@example.com'],
            ['name' => 'Antoine', 'email' => 'antoine@example.com'],
            ['name' => 'Brandon', 'email' => 'brandon@example.com'],
            ['name' => 'Thomas',  'email' => 'thomas@example.com'],
            ['name' => 'API',    'email' => 'api@example.com'],
        ];

        foreach ($users as $userData) {
            User::factory()
                ->create([
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'company_id' => $goofyCompany->id,
                ]);
        }

        $charlieCompany = Company::where('name', 'Charlie Kirk')->first();

        if ($charlieCompany !== null && ! $this->isExcludedCompanyId($charlieCompany->id)) {
            User::factory()
                ->create([
                    'name' => 'Charlie',
                    'email' => 'charlie@example.com',
                    'company_id' => $charlieCompany->id,
                ]);
        }

        $otherCompanies = Company::query()
            ->whereNotIn('name', array_merge(['GoofyTeam', 'Charlie Kirk'], $this->excludedCompanyNames()))
            ->get();

        foreach ($otherCompanies as $company) {
            User::factory()
                ->count(5)
                ->create([
                    'company_id' => $company->id,
                ]);
        }
    }
}
