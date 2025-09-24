<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\MenuCategory;
use Database\Seeders\Concerns\FiltersSeedableCompanies;
use Illuminate\Database\Seeder;

class MenuCategorySeeder extends Seeder
{
    use FiltersSeedableCompanies;

    public function run(): void
    {
        $companies = $this->seedableCompanies();

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
