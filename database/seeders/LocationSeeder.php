<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Location;
use Database\Seeders\Concerns\FiltersSeedableCompanies;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    use FiltersSeedableCompanies;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::where('name', 'GoofyTeam')->first();

        if ($company !== null && ! $this->isExcludedCompanyId($company->id)) {
            // Récupérer les types de localisation de GoofyTeam
            $locationTypes = $company->locationTypes()->get()->keyBy('name');

            // Définir la map entre le nom de la location et son type
            $locationTypeMap = [
                'Chambre froide principale' => 'Réfrigérateur',
                'Congélateur cuisine' => 'Congélateur',
                'Réserve sèche' => 'Autre',
                'Bar' => 'Autre',
                'Cave à vin' => 'Autre',
                'Étagère condiments' => 'Autre',
                'Placard pâtisserie' => 'Autre',
                'Réfrigérateur préparations' => 'Réfrigérateur',
            ];

            // Créer des emplacements pour GoofyTeam avec leurs types
            foreach ($locationTypeMap as $locationName => $typeName) {
                Location::factory()->create([
                    'name' => $locationName,
                    'company_id' => $company->id,
                    'location_type_id' => $locationTypes[$typeName]->id,
                ]);
            }
        }

        // Créer quelques emplacements pour les autres entreprises
        $otherCompanies = Company::query()
            ->where('name', '!=', 'GoofyTeam')
            ->whereNotIn('name', $this->excludedCompanyNames())
            ->get();
        foreach ($otherCompanies as $company) {
            // Récupérer les types de localisation de cette entreprise
            $companyLocationTypes = $company->locationTypes()->get()->keyBy('name');

            // Créer des emplacements avec des types aléatoires
            $typeNames = ['Congélateur', 'Réfrigérateur', 'Autre'];

            for ($i = 0; $i < 3; $i++) {
                $randomTypeName = $typeNames[array_rand($typeNames)];

                Location::factory()->create([
                    'company_id' => $company->id,
                    'location_type_id' => $companyLocationTypes[$randomTypeName]->id,
                ]);
            }
        }
    }
}
