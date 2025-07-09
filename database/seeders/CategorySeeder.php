<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Fruits et Légumes',
            'Viandes et Poissons',
            'Produits Laitiers',
            'Épices et Herbes',
            'Condiments et Sauces',
            'Pains et Pâtisseries',
            'Boissons',
            'Produits Surgelés',
            'Produits Secs',
            'Produits Bio',
            'Produits Locaux',
            'Produits Exotiques',
            'Produits Végétariens',
            'Produits Végans',
            'Produits Sans Gluten',
            'Produits Sans Lactose',
            'Produits Diététiques',
            'Produits de Saison',
            'Produits Artisanaux',
        ];

        $company = Company::where('name', 'GoofyTeam')->first();

        foreach ($categories as $categoryName) {
            Category::factory()->create([
                'name' => $categoryName,
                'company_id' => $company->id,
            ]);
        }

        // Create categories for other companies
        $otherCompanies = Company::where('name', '!=', 'GoofyTeam')->get();
        foreach ($otherCompanies as $company) {
            foreach ($categories as $categoryName) {
                Category::factory()->create([
                    'name' => $categoryName,
                    'company_id' => $company->id,
                ]);
            }
        }
    }
}
