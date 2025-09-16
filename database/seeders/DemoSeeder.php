<?php

namespace Database\Seeders;

use App\Enums\MeasurementUnit;
use App\Models\Category;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Preparation;
use App\Models\User;
use App\Services\ImageService;
use App\Services\OpenFoodFactsService;
use Illuminate\Database\Seeder;
use Throwable;

class DemoSeeder extends Seeder
{
    private const COMPANY_PROFILE = [
        'name' => 'Maison Gustave',
        'open_food_facts_language' => 'fr',
        'users' => [
            ['name' => 'Adrien', 'email' => 'adrien@gmail.com'],
            ['name' => 'Thomas', 'email' => 'thomas@gmail.com'],
            ['name' => 'Luca', 'email' => 'luca@gmail.com'],
            ['name' => 'Brandon', 'email' => 'brandon@gmail.com'],
            ['name' => 'Antoine', 'email' => 'antoine@gmail.com'],
        ],
    ];

    private const MENU_SECTIONS = [
        'hors_doeuvres' => [
            [
                'name' => 'Notre pâté en croûte, pickles de légumes',
                'price' => 28.0,
                'ingredients' => [],
                'preparations' => ['Pâté en croûte', 'Pickles de légumes'],
            ],
            [
                'name' => 'Foie gras de canard, brioche parisienne',
                'price' => 32.0,
                'ingredients' => ['Foie gras de canard cru'],
                'preparations' => ['Brioche parisienne'],
            ],
            [
                'name' => 'Homard bleu rafraîchi, haricots verts et amandes fraîches',
                'price' => 38.0,
                'ingredients' => [
                    'Homard bleu',
                    'Haricots verts frais',
                    'Amandes fraîches',
                    'Huile d’olive',
                    'Citron',
                    'Sel',
                    'Poivre',
                ],
                'preparations' => [],
            ],
            [
                'name' => 'Tomate de plein champs fondante, anchois et basilic',
                'price' => 30.0,
                'ingredients' => [
                    'Tomate de plein champ',
                    'Filets d’anchois',
                    'Basilic frais',
                    'Huile d’olive',
                ],
                'preparations' => [],
            ],
        ],
        'plats' => [
            [
                'name' => 'Dos de bar doré, courgette trompette et jus d’une marinière',
                'price' => 38.0,
                'ingredients' => ['Dos de bar', 'Courgette trompette'],
                'preparations' => ['Jus de marinière'],
            ],
            [
                'name' => 'Sole à la meunière, cassolette d’artichauts (pour deux)',
                'price' => 160.0,
                'ingredients' => [],
                'preparations' => ['Sole à la meunière', 'Cassolette d’artichauts'],
            ],
        ],
        'fromage' => [
            [
                'name' => 'Fromages de France',
                'price' => 16.0,
                'ingredients' => ['Sélection de fromages de vache, chèvre, brebis'],
                'preparations' => [],
            ],
        ],
        'desserts' => [
            [
                'name' => 'Millefeuille classique à la vanille',
                'price' => 14.0,
                'ingredients' => [],
                'preparations' => ['Millefeuille'],
            ],
            [
                'name' => 'Pêche Melba',
                'price' => 14.0,
                'ingredients' => ['Glace vanille'],
                'preparations' => ['Pêches pochées', 'Coulis de framboise'],
            ],
        ],
    ];

    private const MENU_CATEGORY_LABELS = [
        'hors_doeuvres' => 'Entrées de la Maison',
        'plats' => 'Plats Signature',
        'fromage' => 'Sélection Fromagère',
        'desserts' => 'Douceurs sucrées',
    ];

    private const MENU_TYPE_MAP = [
        'hors_doeuvres' => 'entrée',
        'plats' => 'plat',
        'fromage' => 'dessert',
        'desserts' => 'dessert',
    ];

    private const PREPARATION_COMPONENTS = [
        'Pâte brisée' => [
            ['ingredient' => 'Farine'],
            ['ingredient' => 'Beurre'],
            ['ingredient' => 'Eau'],
            ['ingredient' => 'Sel'],
        ],
        'Farce de porc maison' => [
            ['ingredient' => 'Échine de porc'],
            ['ingredient' => 'Veau'],
            ['ingredient' => 'Foie de volaille'],
            ['ingredient' => 'Œufs'],
            ['ingredient' => 'Crème'],
            ['ingredient' => 'Sel'],
            ['ingredient' => 'Poivre'],
            ['ingredient' => 'Armagnac'],
            ['ingredient' => 'Épices'],
        ],
        'Gelée' => [
            ['ingredient' => 'Fond de volaille'],
            ['ingredient' => 'Gélatine'],
        ],
        'Pâté en croûte' => [
            ['preparation' => 'Pâte brisée'],
            ['preparation' => 'Farce de porc maison'],
            ['preparation' => 'Gelée'],
        ],
        'Marinade' => [
            ['ingredient' => 'Vinaigre blanc'],
            ['ingredient' => 'Eau'],
            ['ingredient' => 'Sucre'],
            ['ingredient' => 'Sel'],
            ['ingredient' => 'Graines de moutarde'],
            ['ingredient' => 'Poivre'],
        ],
        'Pickles de légumes' => [
            ['ingredient' => 'Carottes'],
            ['ingredient' => 'Chou-fleur'],
            ['ingredient' => 'Oignons'],
            ['ingredient' => 'Cornichons'],
            ['preparation' => 'Marinade'],
        ],
        'Brioche parisienne' => [
            ['ingredient' => 'Farine'],
            ['ingredient' => 'Œufs'],
            ['ingredient' => 'Beurre'],
            ['ingredient' => 'Lait'],
            ['ingredient' => 'Sucre'],
            ['ingredient' => 'Levure de boulanger'],
            ['ingredient' => 'Sel'],
        ],
        'Jus de marinière' => [
            ['ingredient' => 'Vin blanc sec'],
            ['ingredient' => 'Échalotes'],
            ['ingredient' => 'Beurre'],
            ['ingredient' => 'Persil'],
            ['ingredient' => 'Sel'],
            ['ingredient' => 'Poivre'],
        ],
        'Sole à la meunière' => [
            ['ingredient' => 'Sole'],
            ['ingredient' => 'Beurre'],
            ['ingredient' => 'Farine'],
            ['ingredient' => 'Jus de citron'],
            ['ingredient' => 'Persil'],
        ],
        'Cassolette d’artichauts' => [
            ['ingredient' => 'Artichauts frais'],
            ['ingredient' => 'Fond de volaille'],
            ['ingredient' => 'Huile d’olive'],
            ['ingredient' => 'Ail'],
            ['ingredient' => 'Sel'],
            ['ingredient' => 'Poivre'],
        ],
        'Pâte feuilletée' => [
            ['ingredient' => 'Farine'],
            ['ingredient' => 'Beurre'],
            ['ingredient' => 'Eau'],
            ['ingredient' => 'Sel'],
        ],
        'Crème pâtissière à la vanille' => [
            ['ingredient' => 'Lait'],
            ['ingredient' => 'Sucre'],
            ['ingredient' => 'Jaunes d’œuf'],
            ['ingredient' => 'Fécule'],
            ['ingredient' => 'Gousse de vanille'],
        ],
        'Millefeuille' => [
            ['preparation' => 'Pâte feuilletée'],
            ['preparation' => 'Crème pâtissière à la vanille'],
            ['ingredient' => 'Sucre glace'],
        ],
        'Pêches pochées' => [
            ['ingredient' => 'Pêches'],
            ['ingredient' => 'Sirop'],
            ['ingredient' => 'Vanille'],
        ],
        'Coulis de framboise' => [
            ['ingredient' => 'Framboises'],
            ['ingredient' => 'Sucre'],
        ],
        'Pêche Melba' => [
            ['preparation' => 'Pêches pochées'],
            ['preparation' => 'Coulis de framboise'],
            ['ingredient' => 'Glace vanille'],
        ],
    ];

    private const INGREDIENTS = [
        'Farine' => ['category' => 'Farines', 'unit' => MeasurementUnit::GRAM, 'barcode' => '4056489565536'],
        'Beurre' => ['category' => 'Produits Laitiers', 'unit' => MeasurementUnit::GRAM, 'barcode' => '26064413'],
        'Eau' => ['category' => 'Boissons', 'unit' => MeasurementUnit::LITRE, 'barcode' => '1234500001857'],
        'Sel' => ['category' => 'Épicerie', 'unit' => MeasurementUnit::GRAM, 'barcode' => '10020811'],
        'Échine de porc' => ['category' => 'Viandes', 'unit' => MeasurementUnit::GRAM, 'barcode' => '0207024022173'],
        'Veau' => ['category' => 'Viandes', 'unit' => MeasurementUnit::GRAM, 'barcode' => '2695314012009'],
        'Foie de volaille' => ['category' => 'Viandes', 'unit' => MeasurementUnit::GRAM, 'barcode' => '0215085018561'],
        'Œufs' => ['category' => 'Œufs', 'unit' => MeasurementUnit::UNIT, 'barcode' => '3560070432080'],
        'Crème' => ['category' => 'Produits Laitiers', 'unit' => MeasurementUnit::LITRE, 'barcode' => '3258561419299'],
        'Poivre' => ['category' => 'Épices', 'unit' => MeasurementUnit::GRAM, 'barcode' => '8720254531779'],
        'Armagnac' => ['category' => 'Spiritueux', 'unit' => MeasurementUnit::LITRE, 'barcode' => '3560070575480'],
        'Épices' => ['category' => 'Épices', 'unit' => MeasurementUnit::GRAM, 'barcode' => '3700483800544'],
        'Fond de volaille' => ['category' => 'Épicerie', 'unit' => MeasurementUnit::GRAM, 'barcode' => '3256225451647'],
        'Gélatine' => ['category' => 'Épicerie', 'unit' => MeasurementUnit::GRAM, 'barcode' => '3256225731978'],
        'Carottes' => ['category' => 'Légumes', 'unit' => MeasurementUnit::GRAM, 'barcode' => '3596710431151'],
        'Chou-fleur' => ['category' => 'Légumes', 'unit' => MeasurementUnit::GRAM, 'barcode' => '3560070122349'],
        'Oignons' => ['category' => 'Légumes', 'unit' => MeasurementUnit::GRAM, 'barcode' => '3363290420116'],
        'Cornichons' => ['category' => 'Épicerie', 'unit' => MeasurementUnit::GRAM, 'barcode' => '4061464817722'],
        'Vinaigre blanc' => ['category' => 'Épicerie', 'unit' => MeasurementUnit::LITRE, 'barcode' => '3077311522405'],
        'Sucre' => ['category' => 'Épicerie', 'unit' => MeasurementUnit::GRAM, 'barcode' => '3596710473557'],
        'Graines de moutarde' => ['category' => 'Épices', 'unit' => MeasurementUnit::GRAM, 'barcode' => '7610845400434'],
        'Foie gras de canard cru' => ['category' => 'Viandes', 'unit' => MeasurementUnit::GRAM, 'barcode' => '26078410'],
        'Lait' => ['category' => 'Produits Laitiers', 'unit' => MeasurementUnit::LITRE, 'barcode' => '3428272970017'],
        'Levure de boulanger' => ['category' => 'Épicerie', 'unit' => MeasurementUnit::GRAM, 'barcode' => '2006050036622'],
        'Homard bleu' => ['category' => 'Fruits de Mer', 'unit' => MeasurementUnit::UNIT, 'barcode' => '3770000648317'],
        'Haricots verts frais' => ['category' => 'Légumes', 'unit' => MeasurementUnit::GRAM, 'barcode' => '3760086270076'],
        'Amandes fraîches' => ['category' => 'Fruits secs', 'unit' => MeasurementUnit::GRAM, 'barcode' => '3700194630287'],
        'Huile d’olive' => ['category' => 'Épicerie', 'unit' => MeasurementUnit::LITRE, 'barcode' => '3424096003078'],
        'Citron' => ['category' => 'Fruits', 'unit' => MeasurementUnit::UNIT, 'barcode' => '3256226081881'],
        'Tomate de plein champ' => ['category' => 'Légumes', 'unit' => MeasurementUnit::GRAM, 'barcode' => '3017800246658'],
        'Filets d’anchois' => ['category' => 'Poissons', 'unit' => MeasurementUnit::GRAM, 'barcode' => '3218370591821'],
        'Basilic frais' => ['category' => 'Herbes aromatiques', 'unit' => MeasurementUnit::GRAM, 'barcode' => '3411061111029'],
        'Dos de bar' => ['category' => 'Poissons', 'unit' => MeasurementUnit::UNIT, 'barcode' => '3664335055264'],
        'Courgette trompette' => ['category' => 'Légumes', 'unit' => MeasurementUnit::GRAM, 'barcode' => '2306375001603'],
        'Vin blanc sec' => ['category' => 'Boissons', 'unit' => MeasurementUnit::LITRE, 'barcode' => '3660989151932'],
        'Échalotes' => ['category' => 'Légumes', 'unit' => MeasurementUnit::GRAM, 'barcode' => '8431876150353'],
        'Persil' => ['category' => 'Herbes aromatiques', 'unit' => MeasurementUnit::GRAM, 'barcode' => '2006050101283'],
        'Sole' => ['category' => 'Poissons', 'unit' => MeasurementUnit::UNIT, 'barcode' => '0059749982474'],
        'Jus de citron' => ['category' => 'Épicerie', 'unit' => MeasurementUnit::LITRE, 'barcode' => '3564700299043'],
        'Artichauts frais' => ['category' => 'Légumes', 'unit' => MeasurementUnit::UNIT, 'barcode' => '3256220652766'],
        'Ail' => ['category' => 'Légumes', 'unit' => MeasurementUnit::UNIT, 'barcode' => '3256228100191'],
        'Sucre glace' => ['category' => 'Épicerie', 'unit' => MeasurementUnit::GRAM, 'barcode' => '3220035730001'],
        'Gousse de vanille' => ['category' => 'Épices', 'unit' => MeasurementUnit::UNIT, 'barcode' => '3256225732043'],
        'Jaunes d’œuf' => ['category' => 'Œufs', 'unit' => MeasurementUnit::UNIT, 'barcode' => '3439496001838'],
        'Fécule' => ['category' => 'Farines', 'unit' => MeasurementUnit::GRAM, 'barcode' => '3347431805482'],
        'Pêches' => ['category' => 'Fruits', 'unit' => MeasurementUnit::UNIT, 'barcode' => '3276559409466'],
        'Sirop' => ['category' => 'Épicerie', 'unit' => MeasurementUnit::LITRE, 'barcode' => '5708776000877'],
        'Vanille' => ['category' => 'Épices', 'unit' => MeasurementUnit::GRAM, 'barcode' => '6133798001790'],
        'Framboises' => ['category' => 'Fruits', 'unit' => MeasurementUnit::GRAM, 'barcode' => '3385630118309'],
        'Glace vanille' => ['category' => 'Desserts', 'unit' => MeasurementUnit::GRAM, 'barcode' => '26048154'],
        'Sélection de fromages de vache, chèvre, brebis' => ['category' => 'Fromages', 'unit' => MeasurementUnit::GRAM, 'barcode' => '0200340018370'],
    ];

    private ImageService $images;

    private OpenFoodFactsService $openFoodFacts;

    /** @var array<int, string> */
    private array $missingIngredients = [];

    /** @var array<int, string> */
    private array $missingComponents = [];

    /** @var array<int, string> */
    private array $missingImages = [];

    /** @var array<string, ?string> */
    private array $productImages = [];

    public function __construct(ImageService $images, OpenFoodFactsService $openFoodFacts)
    {
        $this->images = $images;
        $this->openFoodFacts = $openFoodFacts;
    }

    public function run(): void
    {
        $company = Company::updateOrCreate(
            ['name' => self::COMPANY_PROFILE['name']],
            ['open_food_facts_language' => self::COMPANY_PROFILE['open_food_facts_language']]
        );

        $this->seedUsers($company);

        $defaultLocation = $this->resolveDefaultLocation($company);
        $categoryIds = $this->ensureCategories($company);
        $ingredients = $this->seedIngredients($company, $categoryIds, $defaultLocation);
        $preparationCategoryId = $categoryIds['Préparations Maison'] ?? (int) reset($categoryIds);
        $preparations = $this->seedPreparations(
            $company,
            $preparationCategoryId,
            $defaultLocation,
            $ingredients
        );
        $menuCategories = $this->ensureMenuCategories($company);
        $this->seedMenus(
            $company,
            self::MENU_SECTIONS,
            $menuCategories,
            $ingredients,
            $preparations,
            $defaultLocation
        );

        $this->report();
    }

    private function seedUsers(Company $company): void
    {
        foreach (self::COMPANY_PROFILE['users'] as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'company_id' => $company->id,
                    'password' => 'password',
                ]
            );
        }
    }

    private function resolveDefaultLocation(Company $company): Location
    {
        $desiredName = 'Chambre froide Maison Gustave';

        $location = $company->locations()->firstWhere('name', $desiredName);
        if ($location instanceof Location) {
            return $location;
        }

        $existing = $company->locations()->firstWhere('name', 'Réfrigérateur');
        if ($existing instanceof Location) {
            $existing->update(['name' => $desiredName]);

            return $existing->refresh();
        }

        $type = $company->locationTypes()->firstWhere('name', 'Réfrigérateur')
            ?? $company->locationTypes()->first();

        return $company->locations()->create([
            'name' => $desiredName,
            'location_type_id' => $type?->id,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function ensureCategories(Company $company): array
    {
        $names = array_map(
            fn (array $meta) => $meta['category'] ?? 'Ingrédients Divers',
            self::INGREDIENTS
        );
        $names[] = 'Préparations Maison';
        $names[] = 'Ingrédients Divers';
        $names = array_values(array_unique($names));

        $categories = [];
        foreach ($names as $name) {
            $category = Category::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $name,
                ],
                []
            );

            $categories[$name] = $category->id;
        }

        return $categories;
    }

    /**
     * @return array<string, Ingredient>
     */
    private function seedIngredients(Company $company, array $categoryIds, Location $defaultLocation): array
    {
        $ingredients = [];
        $fallbackCategoryId = $categoryIds['Ingrédients Divers'] ?? (int) reset($categoryIds);

        foreach (self::INGREDIENTS as $name => $meta) {
            $categoryId = $categoryIds[$meta['category']] ?? $fallbackCategoryId;

            $ingredient = Ingredient::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $name,
                ],
                [
                    'category_id' => $categoryId,
                    'unit' => $meta['unit']->value,
                    'base_quantity' => 0,
                    'base_unit' => $meta['unit']->value,
                    'barcode' => $meta['barcode'] ?? null,
                ]
            );

            if (empty($ingredient->image_url) && ! empty($meta['barcode'])) {
                $imagePath = $this->storeImageFromOpenFoodFacts($name, $meta['barcode']);
                if ($imagePath) {
                    $ingredient->update(['image_url' => $imagePath]);
                }
            }

            $ingredient->locations()->syncWithoutDetaching([
                $defaultLocation->id => ['quantity' => 0],
            ]);

            $ingredients[$name] = $ingredient;
        }

        return $ingredients;
    }

    private function storeImageFromOpenFoodFacts(string $ingredientName, string $barcode): ?string
    {
        if (isset($this->productImages[$barcode])) {
            return $this->productImages[$barcode];
        }

        try {
            $data = $this->openFoodFacts->searchByBarcode($barcode);
        } catch (Throwable $exception) {
            $this->missingImages[] = $ingredientName.' (erreur API)';

            return $this->productImages[$barcode] = null;
        }

        if (! is_array($data)) {
            $this->missingImages[] = $ingredientName.' (produit introuvable)';

            return $this->productImages[$barcode] = null;
        }

        $product = $data['product'] ?? $data;
        $imageUrl = null;

        if (is_array($product)) {
            $imageUrl = $product['image_front_url']
                ?? $product['image_url']
                ?? null;
        }

        if (! is_string($imageUrl) || ! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $this->missingImages[] = $ingredientName.' (image manquante)';

            return $this->productImages[$barcode] = null;
        }

        try {
            $stored = $this->images->storeFromUrl($imageUrl, 'ingredients');
        } catch (Throwable $exception) {
            $this->missingImages[] = $ingredientName.' (téléchargement)';

            return $this->productImages[$barcode] = null;
        }

        return $this->productImages[$barcode] = $stored;
    }

    /**
     * @param  array<string, Ingredient>  $ingredients
     * @return array<string, Preparation>
     */
    private function seedPreparations(
        Company $company,
        int $categoryId,
        Location $defaultLocation,
        array $ingredients
    ): array {
        $cache = [];

        foreach (array_keys(self::PREPARATION_COMPONENTS) as $name) {
            $this->buildPreparation(
                $name,
                $company,
                $categoryId,
                $defaultLocation,
                $ingredients,
                $cache
            );
        }

        return $cache;
    }

    /**
     * @param  array<string, Ingredient>  $ingredients
     * @param  array<string, Preparation>  $cache
     */
    private function buildPreparation(
        string $name,
        Company $company,
        int $categoryId,
        Location $defaultLocation,
        array $ingredients,
        array &$cache
    ): ?Preparation {
        if (isset($cache[$name])) {
            return $cache[$name];
        }

        $definition = self::PREPARATION_COMPONENTS[$name] ?? null;
        if (! $definition) {
            return null;
        }

        $imagePath = null;
        try {
            $imagePath = $this->images->storePlaceholder();
        } catch (Throwable $exception) {
            $imagePath = null;
        }

        $preparation = Preparation::updateOrCreate(
            [
                'company_id' => $company->id,
                'name' => $name,
            ],
            [
                'category_id' => $categoryId,
                'image_url' => $imagePath,
                'unit' => MeasurementUnit::UNIT->value,
                'base_quantity' => 0,
                'base_unit' => MeasurementUnit::UNIT->value,
            ]
        );

        $preparation->locations()->syncWithoutDetaching([
            $defaultLocation->id => ['quantity' => 0],
        ]);

        $preparation->entities()->delete();

        foreach ($definition as $component) {
            if (isset($component['ingredient'])) {
                $ingredientName = $component['ingredient'];
                $ingredient = $ingredients[$ingredientName] ?? null;
                if (! $ingredient) {
                    $this->missingIngredients[] = $ingredientName.' (préparation '.$name.')';

                    continue;
                }

                $preparation->entities()->create([
                    'entity_id' => $ingredient->id,
                    'entity_type' => Ingredient::class,
                    'location_id' => $defaultLocation->id,
                    'quantity' => 0,
                    'unit' => $ingredient->unit->value,
                ]);

                continue;
            }

            if (isset($component['preparation'])) {
                $childName = $component['preparation'];
                $child = $this->buildPreparation(
                    $childName,
                    $company,
                    $categoryId,
                    $defaultLocation,
                    $ingredients,
                    $cache
                );

                if (! $child) {
                    $this->missingComponents[] = $childName.' (préparation '.$name.')';

                    continue;
                }

                $preparation->entities()->create([
                    'entity_id' => $child->id,
                    'entity_type' => Preparation::class,
                    'location_id' => $defaultLocation->id,
                    'quantity' => 0,
                    'unit' => MeasurementUnit::UNIT->value,
                ]);
            }
        }

        return $cache[$name] = $preparation;
    }

    /**
     * @return array<string, MenuCategory>
     */
    private function ensureMenuCategories(Company $company): array
    {
        $result = [];

        foreach (self::MENU_CATEGORY_LABELS as $key => $label) {
            $result[$key] = MenuCategory::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $label,
                ],
                []
            );
        }

        return $result;
    }

    /**
     * @param  array<string, MenuCategory>  $menuCategories
     * @param  array<string, Ingredient>  $ingredients
     * @param  array<string, Preparation>  $preparations
     * @param  array<string, array<int, array{name: string, price: float, ingredients: array<int, string>, preparations: array<int, string>}>>  $dataset
     */
    private function seedMenus(
        Company $company,
        array $dataset,
        array $menuCategories,
        array $ingredients,
        array $preparations,
        Location $defaultLocation
    ): void {
        foreach ($dataset as $section => $entries) {
            $menuCategory = $menuCategories[$section] ?? null;
            $menuType = self::MENU_TYPE_MAP[$section] ?? 'plat';

            foreach ($entries as $entry) {
                $menu = Menu::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'name' => $entry['name'],
                    ],
                    [
                        'description' => null,
                        'image_url' => null,
                        'is_a_la_carte' => true,
                        'type' => $menuType,
                        'price' => $entry['price'],
                    ]
                );

                if ($menuCategory instanceof MenuCategory) {
                    $menu->categories()->syncWithoutDetaching([$menuCategory->id]);
                }

                foreach ($entry['ingredients'] as $ingredientName) {
                    $ingredient = $ingredients[$ingredientName] ?? null;
                    if (! $ingredient) {
                        $this->missingComponents[] = $ingredientName.' (menu '.$entry['name'].')';

                        continue;
                    }

                    $locationId = $ingredient->locations()->first()?->id ?? $defaultLocation->id;

                    MenuItem::updateOrCreate(
                        [
                            'menu_id' => $menu->id,
                            'entity_id' => $ingredient->id,
                            'entity_type' => Ingredient::class,
                        ],
                        [
                            'location_id' => $locationId,
                            'quantity' => 0,
                            'unit' => $ingredient->unit->value,
                        ]
                    );
                }

                foreach ($entry['preparations'] as $preparationName) {
                    $preparation = $preparations[$preparationName] ?? null;
                    if (! $preparation) {
                        $this->missingComponents[] = $preparationName.' (menu '.$entry['name'].')';

                        continue;
                    }

                    $locationId = $preparation->locations()->first()?->id ?? $defaultLocation->id;

                    MenuItem::updateOrCreate(
                        [
                            'menu_id' => $menu->id,
                            'entity_id' => $preparation->id,
                            'entity_type' => Preparation::class,
                        ],
                        [
                            'location_id' => $locationId,
                            'quantity' => 0,
                            'unit' => MeasurementUnit::UNIT->value,
                        ]
                    );
                }
            }
        }
    }

    private function report(): void
    {
        if (! empty($this->missingIngredients)) {
            $this->command?->warn('Ingrédients manquants : '.implode(', ', array_unique($this->missingIngredients)));
        }

        if (! empty($this->missingComponents)) {
            $this->command?->warn('Références indisponibles : '.implode(', ', array_unique($this->missingComponents)));
        }

        if (! empty($this->missingImages)) {
            $this->command?->warn('Images non récupérées : '.implode(', ', array_unique($this->missingImages)));
        }
    }
}
