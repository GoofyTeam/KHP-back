<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Company::factory()->create(['name' => 'GoofyTeam']);
        Company::factory()->count(9)->create();

        Company::query()
            ->where(function ($query) {
                $query->whereNull('public_card_url')
                    ->orWhere('public_card_url', '')
                    ->orWhere('public_card_url', 'like', 'temp-%');
            })
            ->each(function (Company $company) {
                $company->update([
                    'public_card_url' => sprintf('%d-%s', $company->id, Str::slug($company->name)),
                ]);
            });
    }
}
