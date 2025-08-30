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
            'Viandes Rouges',
            'Viandes Blanches',
            'Poissons',
            'Fruits de Mer',
            'Légumes',
            'Fruits',
            'Produits Laitiers',
            'Céréales et Pâtes',
            'Épices et Herbes',
            'Condiments et Sauces',
            'Boissons',
            'Produits Surgelés',
            'Produits en Conserves',
            'Produits Bio',
            'Snacks et Apéritifs',
            'Pains et Viennoiseries',
            'Desserts et Pâtisseries',
            'Ingrédients Divers',
            'Salades',
            'Soupes',
            'Plats Préparés',
            'Végétariens',
            'Végétaliens',
            'Sans Gluten',
            'Sans Lactose',
            'Sauces',
            'Marinades',
            'Huiles et Vinaigres',
            'Fromages',
            'Charcuterie',
            'Œufs',
            'Noix et Graines',
            'Légumineuses',
            'Céréales',
            'Pâtes',
            'Riz',
            'Farines',
            'Sucre et Édulcorants',
            'Chocolat et Cacao',
            'Café et Thé',
            'Jus de Fruits',
            'Sodas',
            'Eaux Minérales',
            'Bières',
            'Vins',
            'Spiritueux',
            'Cocktails',
            'Produits Exotiques',
            'Autres',
        ];

        $company = Company::where('name', 'GoofyTeam')->first();
        $types = $company->locationTypes()->get()->keyBy('name');

        foreach ($categories as $categoryName) {
            $category = Category::create([
                'name' => $categoryName,
                'company_id' => $company->id,
            ]);

            $category->locationTypes()->attach([
                $types['Réfrigérateur']->id => ['shelf_life_hours' => 48],
                $types['Congélateur']->id => ['shelf_life_hours' => 168],
            ]);
        }

        // Create categories for other companies
        $otherCompanies = Company::where('name', '!=', 'GoofyTeam')->get();
        foreach ($otherCompanies as $company) {
            $types = $company->locationTypes()->get()->keyBy('name');

            foreach ($categories as $categoryName) {
                $category = Category::create([
                    'name' => $categoryName,
                    'company_id' => $company->id,
                ]);

                $category->locationTypes()->attach([
                    $types['Réfrigérateur']->id => ['shelf_life_hours' => 48],
                    $types['Congélateur']->id => ['shelf_life_hours' => 168],
                ]);
            }
        }
    }
}
