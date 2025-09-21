<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $goofyCompany = Company::where('name', 'GoofyTeam')->first();

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

        User::factory()
            ->create([
                'name' => 'Charlie',
                'email' => 'charlie@example.com',
                'company_id' => $charlieCompany->id,
            ]);

        $otherCompanies = Company::whereNotIn('name', ['GoofyTeam', 'Charlie Kirk'])->get();

        foreach ($otherCompanies as $company) {
            User::factory()
                ->count(5)
                ->create([
                    'company_id' => $company->id,
                ]);
        }
    }
}
