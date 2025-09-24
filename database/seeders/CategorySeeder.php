<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use Database\Seeders\Concerns\FiltersSeedableCompanies;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class CategorySeeder extends Seeder
{
    use FiltersSeedableCompanies;

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

        if ($company !== null && ! $this->isExcludedCompanyId($company->id)) {
            $this->seedCategoriesForCompany($company, $categories);
        }

        // Create categories for other companies
        $otherCompanies = Company::query()
            ->where('name', '!=', 'GoofyTeam')
            ->whereNotIn('name', $this->excludedCompanyNames())
            ->get();
        foreach ($otherCompanies as $company) {
            $this->seedCategoriesForCompany($company, $categories);
        }
    }

    /**
     * @param  list<string>  $categories
     */
    private function seedCategoriesForCompany(Company $company, array $categories): void
    {
        $types = $company->locationTypes()->get()->keyBy('name');

        foreach ($categories as $categoryName) {
            $category = Category::firstOrCreate([
                'name' => $categoryName,
                'company_id' => $company->id,
            ]);

            $this->syncCategoryShelfLife($category, $types);
        }
    }

    private function syncCategoryShelfLife(Category $category, Collection $types): void
    {
        $payload = [];

        $refrigerator = $types->get('Réfrigérateur');
        if ($refrigerator !== null) {
            $payload[$refrigerator->id] = ['shelf_life_hours' => 48];
        }

        $freezer = $types->get('Congélateur');
        if ($freezer !== null) {
            $payload[$freezer->id] = ['shelf_life_hours' => 168];
        }

        if ($payload !== []) {
            $category->locationTypes()->sync($payload, false);
        }
    }
}
