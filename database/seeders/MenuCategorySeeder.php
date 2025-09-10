<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\MenuCategory;
use Illuminate\Database\Seeder;

class MenuCategorySeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::all();

        foreach ($companies as $company) {
            foreach (['halal', 'casher', 'vegan'] as $name) {
                MenuCategory::create([
                    'name' => $name,
                    'company_id' => $company->id,
                ]);
            }
        }
    }
}
