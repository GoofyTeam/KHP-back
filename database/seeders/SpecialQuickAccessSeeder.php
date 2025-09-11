<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\SpecialQuickAccess;
use Illuminate\Database\Seeder;

class SpecialQuickAccessSeeder extends Seeder
{
    public function run(): void
    {
        $default = [
            'name' => 'Move Quantity',
            'url' => 'move_quantity',
        ];

        Company::all()->each(function (Company $company) use ($default) {
            SpecialQuickAccess::updateOrCreate(
                ['company_id' => $company->id],
                $default
            );
        });
    }
}
