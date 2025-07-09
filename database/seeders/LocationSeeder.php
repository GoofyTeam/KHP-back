<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::where('name', 'GoofyTeam')->first();

        // Créer des emplacements pour GoofyTeam
        $locations = [
            'Chambre froide principale',
            'Congélateur cuisine',
            'Réserve sèche',
            'Bar',
            'Cave à vin',
            'Étagère condiments',
            'Placard pâtisserie',
            'Réfrigérateur préparations',
        ];

        foreach ($locations as $locationName) {
            Location::factory()->create([
                'name' => $locationName,
                'company_id' => $company->id,
            ]);
        }

        // Créer quelques emplacements pour les autres entreprises
        $otherCompanies = Company::where('name', '!=', 'GoofyTeam')->get();
        foreach ($otherCompanies as $company) {
            Location::factory()
                ->count(3)
                ->create([
                    'company_id' => $company->id,
                ]);
        }
    }
}
