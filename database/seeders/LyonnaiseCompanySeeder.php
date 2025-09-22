<?php

namespace Database\Seeders;

use App\Enums\MeasurementUnit;
use App\Enums\MenuServiceType;
use App\Enums\OrderStatus;
use App\Enums\OrderStepStatus;
use App\Enums\StepMenuStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuType;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderStep;
use App\Models\Preparation;
use App\Models\PreparationEntity;
use App\Models\Room;
use App\Models\StepMenu;
use App\Models\StockMovement;
use App\Models\Table;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class LyonnaiseCompanySeeder extends Seeder
{
    private const FALLBACK_IMAGE_URL = 'https://uniiti.com/images/shops/slides/eff49d9e5de18863ee53cc14d675dba6c0eef27c.jpeg';

    private const IMAGE_MAX_BYTES = 6_291_456; // ≈6 MB to accommodate high-resolution seed images

    private const IMAGE_MAP = [
        'ingredient' => [
            // produits secs et frais
            'Farine de blé T45 des Monts du Lyonnais' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/df/Wheat_flour.jpg/2560px-Wheat_flour.jpg',
            'Œufs plein air calibre M' => 'https://upload.wikimedia.org/wikipedia/commons/0/0e/Chicken_eggs.jpg',
            'Levure boulangère fraîche' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e9/Compressed_fresh_yeast_-_1.jpg/1280px-Compressed_fresh_yeast_-_1.jpg',
            'Lait entier de ferme' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/38/Glass_of_milk.jpg/1280px-Glass_of_milk.jpg',
            'Beurre doux AOP' => 'https://upload.wikimedia.org/wikipedia/commons/d/d2/Butter_250_g.jpg',
            'Sel fin de Guérande' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/45/Table_salt_fine_grain_V1.jpg/2475px-Table_salt_fine_grain_V1.jpg',

            // ✅ remplacés par tes liens
            'Sucre cristal' => 'https://media.carrefour.fr/medias/342ac64119a847faa87b8d1f5cae5696/p_1500x1500/03165430810005_C1N1_s34.png',
            'Crème liquide 35%' => 'https://www.elle-et-vire.com/uploads/cache/400x400/uploads/recip/product/141/67ee8010866ff_whipping-cream-vdef.png',
            'Pommes de terre Agata' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/34/Pomme_de_terre_Agata.jpg/1920px-Pomme_de_terre_Agata.jpg',
            'Noix de muscade moulue' => 'https://www.ducros.com/-/media/project/oneweb/ducros/products/muscade-moulue-800x800.webp?rev=2e796a03d413462e874a4a21697444f5&vd=20250422T003911Z&extension=webp&hash=9FCDF071A8FDEBCFE8958B4E1FCAB5BF',
            'Quenelles de brochet artisanales' => 'https://www.bobosse.fr/wp-content/uploads/2022/08/Quenelle_de_brochet.jpg',
            'Bisque d’écrevisse maison' => 'https://www.lapetitecuisinedenat.com/wp-content/uploads/2023/07/bisque-ecrevisse.jpg.webp',
            'Crème fraîche fermière' => 'https://www.le-meilleur-de-chez-nous.fr/wp-content/uploads/2022/05/creme-fraiche-500-grammes-scaled-e1726555615926.jpg',
            'Saucisson à cuire lyonnais' => 'https://www.bobosse.fr/wp-content/uploads/2022/08/29-1.jpg',
            'Rosette de Lyon artisanale' => 'https://maison-duculty.fr/wp-content/uploads/2025/01/1204.webp',
            'Terrine de campagne maison' => 'https://www.maspatule.com/blog/wp-content/uploads/2022/12/PATE-1-800x1200.jpg',
            'Cornichons aigre-doux' => 'https://assets.afcdn.com/recipe/20131121/63120_w1024h768c1cx3008cy2000.jpg',
            'Salade frisée' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8a/Chicoree_frisee.jpg/1920px-Chicoree_frisee.jpg',
            'Œufs de caille' => 'https://assets.afcdn.com/story/20241025/2273575_w1888h1060c1cx3024cy2012cxt0cyt0cxb6048cyb4024.webp',
            'Lardons fumés de poitrine' => 'https://res.cloudinary.com/hv9ssmzrz/image/fetch/c_fill,f_auto,h_488,q_auto,w_650/https://s3-eu-west-1.amazonaws.com/images-ca-1-0-1-eu/recipe_photos/original/312/tranches-de-lard-3000x2000.jpg',
            'Pain de campagne' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6a/Pain_de_Campagne.jpg/500px-Pain_de_Campagne.jpg',
            'Vinaigre de vin rouge' => 'https://martin-pouret.com/wp-content/uploads/2024/04/Vinaigre-de-vin-rouge.jpg',
            'Huile de noix artisanale' => 'https://www.moulincastagne.com/wp-content/uploads/2022/07/IMG_2704.jpg',
            'Échalotes' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/04/%C3%89chalotes_vari%C3%A9t%C3%A9s.jpg/1920px-%C3%89chalotes_vari%C3%A9t%C3%A9s.jpg',
            'Poivre noir moulu' => 'https://media.carrefour.fr/medias/f99d92e1ca67450bafd0d8f6ae4e190b/p_540x540/3560070762101_0.jpg',
            'Pâte sablée pur beurre' => 'https://assets.afcdn.com/recipe/20180628/79984_w314h314c1cx1944cy2592cxt0cyt0cxb3888cyb5184.webp',

            // boissons (inchangé)
            'Coca-Cola 33cl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/bd/Un_Coca-Cola.jpg/640px-Un_Coca-Cola.jpg',
            'Fanta Orange 33cl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f4/Zero_Sugar_Fanta_%28cropped%29.jpg/640px-Zero_Sugar_Fanta_%28cropped%29.jpg',
            'Thé à la menthe 50cl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1a/Lipton-mug-tea.jpg/640px-Lipton-mug-tea.jpg',

            'Pralines roses concassées' => 'https://media.carrefour.fr/medias/7a6daaad9b6948aeaa92c4471abf1c27/p_1500x1500/03179142794027_C1N1_s09.png',
            'Ail rose' => 'https://www.ailrosedelautrec.com/wp-content/uploads/2024/02/tetes-1536x1027.png',
            'Moutarde de Dijon' => 'https://paris-jetequitte.com/wp-content/uploads/2023/07/moutarde-dijon-entete.jpg.webp',
        ],
        'preparation' => [
            'Pâte briochée maison' => 'https://www.guydemarle.com/rails/active_storage/representations/eyJfcmFpbHMiOnsibWVzc2FnZSI6IkJBaHBBeWlJQWc9PSIsImV4cCI6bnVsbCwicHVyIjoiYmxvYl9pZCJ9fQ==--2e98b7ca8b1413e37b4516e853d5386683e11342/eyJfcmFpbHMiOnsibWVzc2FnZSI6IkJBaDdCam9VWTI5dFltbHVaVjl2Y0hScGIyNXpld2c2QzNKbGMybDZaVWtpRFRZd01IZzJNREJlQmpvR1JWUTZER2R5WVhacGRIbEpJZ3REWlc1MFpYSUdPd2RVT2dsamNtOXdTU0lRTmpBd2VEWXdNQ3N3S3pBR093ZFUiLCJleHAiOm51bGwsInB1ciI6InZhcmlhdGlvbiJ9fQ==--929a1958a8630156464b089800ca739f1b4570de/pate-briochee-0.jpg',
            'Gratin dauphinois crémeux' => 'https://assets.afcdn.com/recipe/20201217/116563_w314h314c1cx1116cy671cxt0cyt0cxb2232cyb1342.webp',
            'Sauce Nantua maison' => 'https://assets.afcdn.com/recipe/20230620/143613_w600.jpg',
            'Croutons dorés à l’ail' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1f/13-09-01-kochtreffen-wien-Bi-frie-004.jpg/640px-13-09-01-kochtreffen-wien-Bi-frie-004.jpg',
            'Vinaigrette au vin rouge' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d4/Making_vinaigrette.jpg/640px-Making_vinaigrette.jpg',
            'Crème praline onctueuse' => 'https://upload.wikimedia.org/wikipedia/commons/1/15/Cr%C3%A8me_brul%C3%A9e_chocolat_blanc%2C_rhubarbe%2C_glace_praline_%28L%27%C3%A9bullition%2C_Lyon%29.jpg',
        ],
        'menu' => [
            'Salade lyonnaise tradition' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/94/Salad_platter.jpg/640px-Salad_platter.jpg',
            'Planche de cochonnailles des Halles' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/39/Cheese_%26_meat_plate_at_the_girl_%26_the_fig_-_Sarah_Stierch.jpg/640px-Cheese_%26_meat_plate_at_the_girl_%26_the_fig_-_Sarah_Stierch.jpg',
            'Quenelle de brochet, sauce Nantua' => 'https://assets.afcdn.com/recipe/20220223/129337_w600.jpg',
            'Saucisson brioché des gones' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/50/Saucisson_pistach%C3%A9_en_brioche.jpg/640px-Saucisson_pistach%C3%A9_en_brioche.jpg',
            'Tarte praline de grand-mère' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/18/Une_part_de_tarte_aux_pralines.jpg/640px-Une_part_de_tarte_aux_pralines.jpg',
            'Coca-Cola 33cl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/bd/Un_Coca-Cola.jpg/640px-Un_Coca-Cola.jpg',
            'Fanta Orange 33cl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f4/Zero_Sugar_Fanta_%28cropped%29.jpg/640px-Zero_Sugar_Fanta_%28cropped%29.jpg',
            'Thé à la menthe 50cl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1a/Lipton-mug-tea.jpg/640px-Lipton-mug-tea.jpg',
        ],
    ];

    private ImageService $imageService;

    /**
     * @var array<string, array<string, string>>
     */
    private array $imageCache = [];

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function run(): void
    {
        DB::transaction(function () {
            $company = Company::firstOrCreate(
                ['name' => 'La Table des Canuts'],
                [
                    'open_food_facts_language' => 'fr',
                    'show_out_of_stock_menus_on_card' => false,
                    'show_menu_images' => true,
                ]
            );

            $locations = $this->ensureLocations($company);
            $categories = $this->ensureCategories($company);
            $menuCategories = $this->ensureMenuCategories($company);
            $ingredients = $this->seedIngredients($company, $categories, $locations);
            $preparations = $this->seedPreparations($company, $categories, $locations, $ingredients);
            $menus = $this->seedMenus($company, $menuCategories, $locations, $ingredients, $preparations);
            $tables = $this->ensureDiningLayout($company);
            $this->seedStockMovements($ingredients, $preparations);
            $user = $this->ensureUser($company);
            $this->seedOrders($company, $tables, $menus, $user);

            if ($company->public_menu_card_url !== 'la-table-des-canuts') {
                $company->forceFill(['public_menu_card_url' => 'la-table-des-canuts'])->saveQuietly();
            }
        });
    }

    private function ensureUser(Company $company): User
    {
        return User::updateOrCreate(
            ['email' => 'user@lyonnaise.com'],
            [
                'name' => 'Responsable Lyonnaise',
                'company_id' => $company->id,
                'password' => 'password',
            ]
        );
    }

    /**
     * @return array<string, Table>
     */
    private function ensureDiningLayout(Company $company): array
    {
        $room = Room::updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'BEL',
            ],
            [
                'name' => 'Salle Bellecour',
                'code' => 'BEL',
            ]
        );

        $tables = [
            ['label' => 'B1', 'seats' => 2],
            ['label' => 'B2', 'seats' => 4],
            ['label' => 'B3', 'seats' => 4],
            ['label' => 'B4', 'seats' => 6],
        ];

        $map = [];

        foreach ($tables as $table) {
            $record = Table::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'room_id' => $room->id,
                    'label' => $table['label'],
                ],
                [
                    'seats' => $table['seats'],
                ]
            );

            $map[$table['label']] = $record;
        }

        return $map;
    }

    /**
     * @return array<string, \App\Models\Location>
     */
    private function ensureLocations(Company $company): array
    {
        $locationTypes = $company->locationTypes()->get()->keyBy('name');

        $definitions = [
            'Congélateur' => 'Congélateur',
            'Réfrigérateur' => 'Réfrigérateur',
            'Réserve sèche' => 'Autre',
            'Cuisine chaude' => 'Autre',
            'Réfrigérateur de service' => 'Réfrigérateur',
            'Pâtisserie froide' => 'Réfrigérateur',
        ];

        $locations = [];
        foreach ($definitions as $name => $typeName) {
            $fallbackType = $locationTypes->first();
            $location = Location::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $name,
                ],
                [
                    'location_type_id' => $locationTypes[$typeName]->id ?? $fallbackType?->id,
                ]
            );

            $locations[$name] = $location;
        }

        return $locations;
    }

    /**
     * @return array<string, int>
     */
    private function ensureCategories(Company $company): array
    {
        $locationTypes = $company->locationTypes()->get()->keyBy('name');
        $names = [
            'Farines',
            'Œufs',
            'Produits Laitiers',
            'Pains et Viennoiseries',
            'Plats Préparés',
            'Sauces',
            'Desserts et Pâtisseries',
            'Charcuterie',
            'Condiments et Sauces',
            'Légumes',
            'Épices et Herbes',
            'Poissons',
            'Boissons',
            'Ingrédients Divers',
        ];

        $categories = [];
        foreach ($names as $name) {
            $category = Category::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $name,
                ]
            );

            if ($locationTypes->has('Réfrigérateur') && $locationTypes->has('Congélateur')) {
                $category->locationTypes()->syncWithoutDetaching([
                    $locationTypes['Réfrigérateur']->id => ['shelf_life_hours' => 48],
                    $locationTypes['Congélateur']->id => ['shelf_life_hours' => 168],
                ]);
            }

            $categories[$name] = $category->id;
        }

        return $categories;
    }

    /**
     * @return array<string, int>
     */
    private function ensureMenuCategories(Company $company): array
    {
        $names = ['tradition lyonnaise', 'suggestion du chef', 'menu du marché', 'boisson'];
        $map = [];

        foreach ($names as $name) {
            $menuCategory = MenuCategory::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $name,
                ]
            );

            $map[$name] = $menuCategory->id;
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $categories
     * @param  array<string, \App\Models\Location>  $locations
     * @return array<string, Ingredient>
     */
    private function seedIngredients(Company $company, array $categories, array $locations): array
    {
        $definitions = [
            [
                'name' => 'Farine de blé T45 des Monts du Lyonnais',
                'category' => 'Farines',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 20,
                'threshold' => 5,
                'stocks' => [
                    ['location' => 'Réserve sèche', 'quantity' => 18.0],
                ],
            ],
            [
                'name' => 'Œufs plein air calibre M',
                'category' => 'Œufs',
                'unit' => MeasurementUnit::UNIT,
                'base_quantity' => 180,
                'threshold' => 24,
                'stocks' => [
                    ['location' => 'Réfrigérateur de service', 'quantity' => 96],
                    ['location' => 'Réfrigérateur', 'quantity' => 36],
                ],
            ],
            [
                'name' => 'Levure boulangère fraîche',
                'category' => 'Condiments et Sauces',
                'unit' => MeasurementUnit::GRAM,
                'base_quantity' => 500,
                'threshold' => 120,
                'stocks' => [
                    ['location' => 'Réfrigérateur de service', 'quantity' => 320],
                ],
            ],
            [
                'name' => 'Lait entier de ferme',
                'category' => 'Produits Laitiers',
                'unit' => MeasurementUnit::LITRE,
                'base_quantity' => 8,
                'threshold' => 2,
                'stocks' => [
                    ['location' => 'Réfrigérateur de service', 'quantity' => 5.0],
                    ['location' => 'Pâtisserie froide', 'quantity' => 1.2],
                ],
            ],
            [
                'name' => 'Beurre doux AOP',
                'category' => 'Produits Laitiers',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 5,
                'threshold' => 1.5,
                'stocks' => [
                    ['location' => 'Réfrigérateur de service', 'quantity' => 2.1],
                    ['location' => 'Pâtisserie froide', 'quantity' => 0.7],
                ],
            ],
            [
                'name' => 'Sel fin de Guérande',
                'category' => 'Condiments et Sauces',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 3,
                'threshold' => 0.5,
                'stocks' => [
                    ['location' => 'Réserve sèche', 'quantity' => 1.2],
                ],
            ],
            [
                'name' => 'Sucre cristal',
                'category' => 'Desserts et Pâtisseries',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 6,
                'stocks' => [
                    ['location' => 'Réserve sèche', 'quantity' => 3.0],
                ],
            ],
            [
                'name' => 'Pralines roses concassées',
                'category' => 'Desserts et Pâtisseries',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 2,
                'threshold' => 1.0,
                'stocks' => [
                    ['location' => 'Pâtisserie froide', 'quantity' => 0.3],
                    ['location' => 'Réserve sèche', 'quantity' => 0.15],
                ],
            ],
            [
                'name' => 'Crème liquide 35%',
                'category' => 'Produits Laitiers',
                'unit' => MeasurementUnit::LITRE,
                'base_quantity' => 5,
                'threshold' => 0.8,
                'stocks' => [
                    ['location' => 'Pâtisserie froide', 'quantity' => 1.5],
                    ['location' => 'Réfrigérateur de service', 'quantity' => 0.6],
                ],
            ],
            [
                'name' => 'Pommes de terre Agata',
                'category' => 'Légumes',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 25,
                'stocks' => [
                    ['location' => 'Réserve sèche', 'quantity' => 12.0],
                    ['location' => 'Cuisine chaude', 'quantity' => 3.5],
                ],
            ],
            [
                'name' => 'Ail rose',
                'category' => 'Condiments et Sauces',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 2,
                'stocks' => [
                    ['location' => 'Réserve sèche', 'quantity' => 0.5],
                    ['location' => 'Cuisine chaude', 'quantity' => 0.1],
                ],
            ],
            [
                'name' => 'Noix de muscade moulue',
                'category' => 'Épices et Herbes',
                'unit' => MeasurementUnit::GRAM,
                'base_quantity' => 200,
                'stocks' => [
                    ['location' => 'Réserve sèche', 'quantity' => 60],
                ],
            ],
            [
                'name' => 'Quenelles de brochet artisanales',
                'category' => 'Plats Préparés',
                'unit' => MeasurementUnit::UNIT,
                'base_quantity' => 30,
                'threshold' => 10,
                'stocks' => [
                    ['location' => 'Congélateur', 'quantity' => 12],
                    ['location' => 'Cuisine chaude', 'quantity' => 6],
                ],
            ],
            [
                'name' => 'Bisque d’écrevisse maison',
                'category' => 'Sauces',
                'unit' => MeasurementUnit::LITRE,
                'base_quantity' => 3,
                'threshold' => 0.6,
                'stocks' => [
                    ['location' => 'Réfrigérateur de service', 'quantity' => 0.25],
                    ['location' => 'Cuisine chaude', 'quantity' => 0.15],
                ],
            ],
            [
                'name' => 'Crème fraîche fermière',
                'category' => 'Produits Laitiers',
                'unit' => MeasurementUnit::LITRE,
                'base_quantity' => 4,
                'stocks' => [
                    ['location' => 'Réfrigérateur de service', 'quantity' => 0.9],
                    ['location' => 'Cuisine chaude', 'quantity' => 0.4],
                ],
            ],
            [
                'name' => 'Saucisson à cuire lyonnais',
                'category' => 'Charcuterie',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 8,
                'stocks' => [
                    ['location' => 'Réfrigérateur de service', 'quantity' => 1.8],
                    ['location' => 'Cuisine chaude', 'quantity' => 0.8],
                ],
            ],
            [
                'name' => 'Rosette de Lyon artisanale',
                'category' => 'Charcuterie',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 5,
                'stocks' => [
                    ['location' => 'Réfrigérateur de service', 'quantity' => 1.1],
                    ['location' => 'Cuisine chaude', 'quantity' => 0.7],
                ],
            ],
            [
                'name' => 'Terrine de campagne maison',
                'category' => 'Charcuterie',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 3,
                'stocks' => [
                    ['location' => 'Réfrigérateur de service', 'quantity' => 0.9],
                ],
            ],
            [
                'name' => 'Cornichons aigre-doux',
                'category' => 'Condiments et Sauces',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 1.5,
                'stocks' => [
                    ['location' => 'Réfrigérateur de service', 'quantity' => 0.45],
                    ['location' => 'Cuisine chaude', 'quantity' => 0.15],
                ],
            ],
            [
                'name' => 'Salade frisée',
                'category' => 'Légumes',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 4,
                'threshold' => 1.0,
                'stocks' => [
                    ['location' => 'Réfrigérateur de service', 'quantity' => 1.1],
                    ['location' => 'Réfrigérateur', 'quantity' => 0.5],
                ],
            ],
            [
                'name' => 'Œufs de caille',
                'category' => 'Œufs',
                'unit' => MeasurementUnit::UNIT,
                'base_quantity' => 60,
                'stocks' => [
                    ['location' => 'Réfrigérateur de service', 'quantity' => 18],
                    ['location' => 'Réfrigérateur', 'quantity' => 6],
                ],
            ],
            [
                'name' => 'Lardons fumés de poitrine',
                'category' => 'Charcuterie',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 3,
                'stocks' => [
                    ['location' => 'Réfrigérateur de service', 'quantity' => 0.6],
                    ['location' => 'Cuisine chaude', 'quantity' => 0.3],
                ],
            ],
            [
                'name' => 'Pain de campagne',
                'category' => 'Pains et Viennoiseries',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 5,
                'stocks' => [
                    ['location' => 'Réserve sèche', 'quantity' => 1.6],
                    ['location' => 'Cuisine chaude', 'quantity' => 0.8],
                ],
            ],
            [
                'name' => 'Vinaigre de vin rouge',
                'category' => 'Condiments et Sauces',
                'unit' => MeasurementUnit::LITRE,
                'base_quantity' => 3,
                'stocks' => [
                    ['location' => 'Réserve sèche', 'quantity' => 1.1],
                    ['location' => 'Cuisine chaude', 'quantity' => 0.2],
                ],
            ],
            [
                'name' => 'Huile de noix artisanale',
                'category' => 'Condiments et Sauces',
                'unit' => MeasurementUnit::LITRE,
                'base_quantity' => 2,
                'stocks' => [
                    ['location' => 'Réserve sèche', 'quantity' => 0.6],
                    ['location' => 'Cuisine chaude', 'quantity' => 0.18],
                ],
            ],
            [
                'name' => 'Échalotes',
                'category' => 'Légumes',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 3,
                'stocks' => [
                    ['location' => 'Réserve sèche', 'quantity' => 0.6],
                    ['location' => 'Cuisine chaude', 'quantity' => 0.3],
                ],
            ],
            [
                'name' => 'Moutarde de Dijon',
                'category' => 'Condiments et Sauces',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 2,
                'stocks' => [
                    ['location' => 'Réserve sèche', 'quantity' => 0.4],
                ],
            ],
            [
                'name' => 'Poivre noir moulu',
                'category' => 'Épices et Herbes',
                'unit' => MeasurementUnit::GRAM,
                'base_quantity' => 150,
                'stocks' => [
                    ['location' => 'Réserve sèche', 'quantity' => 45],
                ],
            ],
            [
                'name' => 'Pâte sablée pur beurre',
                'category' => 'Desserts et Pâtisseries',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 3,
                'stocks' => [
                    ['location' => 'Pâtisserie froide', 'quantity' => 0.5],
                    ['location' => 'Congélateur', 'quantity' => 0.3],
                ],
            ],
            [
                'name' => 'Coca-Cola 33cl',
                'category' => 'Boissons',
                'unit' => MeasurementUnit::UNIT,
                'base_quantity' => 48,
                'threshold' => 12,
                'stocks' => [
                    ['location' => 'Réfrigérateur de service', 'quantity' => 18],
                    ['location' => 'Réserve sèche', 'quantity' => 12],
                ],
            ],
            [
                'name' => 'Fanta Orange 33cl',
                'category' => 'Boissons',
                'unit' => MeasurementUnit::UNIT,
                'base_quantity' => 36,
                'threshold' => 9,
                'stocks' => [
                    ['location' => 'Réfrigérateur de service', 'quantity' => 14],
                    ['location' => 'Réserve sèche', 'quantity' => 8],
                ],
            ],
            [
                'name' => 'Thé à la menthe 50cl',
                'category' => 'Boissons',
                'unit' => MeasurementUnit::UNIT,
                'base_quantity' => 24,
                'threshold' => 6,
                'stocks' => [
                    ['location' => 'Réfrigérateur de service', 'quantity' => 12],
                ],
            ],
        ];

        $ingredients = [];
        foreach ($definitions as $definition) {
            $ingredient = Ingredient::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $definition['name'],
                ],
                [
                    'category_id' => $categories[$definition['category']] ?? Arr::first($categories),
                    'unit' => $definition['unit']->value,
                    'base_unit' => ($definition['base_unit'] ?? $definition['unit'])->value,
                    'base_quantity' => $definition['base_quantity'],
                    'threshold' => $definition['threshold'] ?? null,
                    'image_url' => $this->ensureImagePath('ingredient', $definition['name']),
                ]
            );

            $stocks = [];
            foreach ($definition['stocks'] as $stock) {
                $location = $locations[$stock['location']] ?? null;
                if ($location) {
                    $stocks[$location->id] = ['quantity' => $stock['quantity']];
                }
            }

            if (! empty($stocks)) {
                $ingredient->locations()->sync($stocks);
            }

            $ingredients[$definition['name']] = $ingredient->fresh(['locations']);
        }

        return $ingredients;
    }

    /**
     * @param  array<string, int>  $categories
     * @param  array<string, \App\Models\Location>  $locations
     * @param  array<string, Ingredient>  $ingredients
     * @return array<string, Preparation>
     */
    private function seedPreparations(Company $company, array $categories, array $locations, array $ingredients): array
    {
        $definitions = [
            [
                'name' => 'Pâte briochée maison',
                'category' => 'Pains et Viennoiseries',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 2.5,
                'components' => [
                    ['name' => 'Farine de blé T45 des Monts du Lyonnais', 'quantity' => 1.5, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réserve sèche'],
                    ['name' => 'Œufs plein air calibre M', 'quantity' => 8, 'unit' => MeasurementUnit::UNIT, 'location' => 'Réfrigérateur de service'],
                    ['name' => 'Beurre doux AOP', 'quantity' => 0.35, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réfrigérateur de service'],
                    ['name' => 'Lait entier de ferme', 'quantity' => 0.5, 'unit' => MeasurementUnit::LITRE, 'location' => 'Réfrigérateur de service'],
                    ['name' => 'Levure boulangère fraîche', 'quantity' => 35, 'unit' => MeasurementUnit::GRAM, 'location' => 'Réfrigérateur de service'],
                    ['name' => 'Sucre cristal', 'quantity' => 0.08, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réserve sèche'],
                    ['name' => 'Sel fin de Guérande', 'quantity' => 18, 'unit' => MeasurementUnit::GRAM, 'location' => 'Réserve sèche'],
                ],
                'stocks' => [
                    ['location' => 'Pâtisserie froide', 'quantity' => 0.9],
                    ['location' => 'Cuisine chaude', 'quantity' => 0.4],
                ],
            ],
            [
                'name' => 'Gratin dauphinois crémeux',
                'category' => 'Plats Préparés',
                'unit' => MeasurementUnit::UNIT,
                'base_quantity' => 8,
                'components' => [
                    ['name' => 'Pommes de terre Agata', 'quantity' => 1.2, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réserve sèche'],
                    ['name' => 'Crème fraîche fermière', 'quantity' => 0.6, 'unit' => MeasurementUnit::LITRE, 'location' => 'Réfrigérateur de service'],
                    ['name' => 'Lait entier de ferme', 'quantity' => 0.4, 'unit' => MeasurementUnit::LITRE, 'location' => 'Réfrigérateur de service'],
                    ['name' => 'Ail rose', 'quantity' => 0.02, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réserve sèche'],
                    ['name' => 'Beurre doux AOP', 'quantity' => 0.1, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réfrigérateur de service'],
                    ['name' => 'Noix de muscade moulue', 'quantity' => 2, 'unit' => MeasurementUnit::GRAM, 'location' => 'Réserve sèche'],
                    ['name' => 'Sel fin de Guérande', 'quantity' => 6, 'unit' => MeasurementUnit::GRAM, 'location' => 'Réserve sèche'],
                ],
                'stocks' => [
                    ['location' => 'Cuisine chaude', 'quantity' => 6],
                    ['location' => 'Réfrigérateur de service', 'quantity' => 0],
                    ['location' => 'Congélateur', 'quantity' => 1.2],
                ],
            ],
            [
                'name' => 'Sauce Nantua maison',
                'category' => 'Sauces',
                'unit' => MeasurementUnit::LITRE,
                'base_quantity' => 1.2,
                'components' => [
                    ['name' => 'Bisque d’écrevisse maison', 'quantity' => 0.6, 'unit' => MeasurementUnit::LITRE, 'location' => 'Réfrigérateur de service'],
                    ['name' => 'Crème liquide 35%', 'quantity' => 0.35, 'unit' => MeasurementUnit::LITRE, 'location' => 'Pâtisserie froide'],
                    ['name' => 'Beurre doux AOP', 'quantity' => 0.08, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réfrigérateur de service'],
                    ['name' => 'Sel fin de Guérande', 'quantity' => 4, 'unit' => MeasurementUnit::GRAM, 'location' => 'Réserve sèche'],
                ],
                'stocks' => [
                    ['location' => 'Cuisine chaude', 'quantity' => 0.35],
                    ['location' => 'Réfrigérateur de service', 'quantity' => 0.18],
                ],
            ],
            [
                'name' => 'Croutons dorés à l’ail',
                'category' => 'Pains et Viennoiseries',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 0.8,
                'components' => [
                    ['name' => 'Pain de campagne', 'quantity' => 0.4, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réserve sèche'],
                    ['name' => 'Beurre doux AOP', 'quantity' => 0.07, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réfrigérateur de service'],
                    ['name' => 'Ail rose', 'quantity' => 0.015, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réserve sèche'],
                ],
                'stocks' => [
                    ['location' => 'Cuisine chaude', 'quantity' => 0.18],
                    ['location' => 'Réserve sèche', 'quantity' => 0.12],
                ],
            ],
            [
                'name' => 'Vinaigrette au vin rouge',
                'category' => 'Sauces',
                'unit' => MeasurementUnit::LITRE,
                'base_quantity' => 0.9,
                'components' => [
                    ['name' => 'Vinaigre de vin rouge', 'quantity' => 0.18, 'unit' => MeasurementUnit::LITRE, 'location' => 'Réserve sèche'],
                    ['name' => 'Huile de noix artisanale', 'quantity' => 0.36, 'unit' => MeasurementUnit::LITRE, 'location' => 'Réserve sèche'],
                    ['name' => 'Échalotes', 'quantity' => 0.06, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réserve sèche'],
                    ['name' => 'Moutarde de Dijon', 'quantity' => 0.04, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réserve sèche'],
                    ['name' => 'Sel fin de Guérande', 'quantity' => 5, 'unit' => MeasurementUnit::GRAM, 'location' => 'Réserve sèche'],
                    ['name' => 'Poivre noir moulu', 'quantity' => 4, 'unit' => MeasurementUnit::GRAM, 'location' => 'Réserve sèche'],
                ],
                'stocks' => [
                    ['location' => 'Réfrigérateur de service', 'quantity' => 0.25],
                    ['location' => 'Cuisine chaude', 'quantity' => 0.1],
                ],
            ],
            [
                'name' => 'Crème praline onctueuse',
                'category' => 'Desserts et Pâtisseries',
                'unit' => MeasurementUnit::KILOGRAM,
                'base_quantity' => 1.4,
                'components' => [
                    ['name' => 'Pralines roses concassées', 'quantity' => 0.35, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Pâtisserie froide'],
                    ['name' => 'Crème liquide 35%', 'quantity' => 0.45, 'unit' => MeasurementUnit::LITRE, 'location' => 'Pâtisserie froide'],
                    ['name' => 'Beurre doux AOP', 'quantity' => 0.05, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réfrigérateur de service'],
                ],
                'stocks' => [
                    ['location' => 'Pâtisserie froide', 'quantity' => 0.4],
                    ['location' => 'Réfrigérateur de service', 'quantity' => 0.08],
                ],
            ],
        ];

        $preparations = [];
        foreach ($definitions as $definition) {
            $preparation = Preparation::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $definition['name'],
                ],
                [
                    'category_id' => $categories[$definition['category']] ?? Arr::first($categories),
                    'unit' => $definition['unit']->value,
                    'base_unit' => ($definition['base_unit'] ?? $definition['unit'])->value,
                    'base_quantity' => $definition['base_quantity'],
                    'image_url' => $this->ensureImagePath('preparation', $definition['name']),
                ]
            );

            $preparation->entities()->delete();

            foreach ($definition['components'] as $component) {
                $entity = $ingredients[$component['name']] ?? null;
                if (! $entity) {
                    continue;
                }

                $location = $locations[$component['location']] ?? $entity->locations->first();
                if (! $location) {
                    continue;
                }

                PreparationEntity::create([
                    'preparation_id' => $preparation->id,
                    'entity_id' => $entity->id,
                    'entity_type' => Ingredient::class,
                    'location_id' => $location->id,
                    'quantity' => $component['quantity'],
                    'unit' => $component['unit']->value,
                ]);
            }

            $stocks = [];
            foreach ($definition['stocks'] as $stock) {
                $location = $locations[$stock['location']] ?? null;
                if ($location) {
                    $stocks[$location->id] = ['quantity' => $stock['quantity']];
                }
            }

            if (! empty($stocks)) {
                $preparation->locations()->sync($stocks);
            }

            $preparations[$definition['name']] = $preparation->fresh(['locations']);
        }

        return $preparations;
    }

    /**
     * @param  array<string, int>  $menuCategories
     * @param  array<string, \App\Models\Location>  $locations
     * @param  array<string, Ingredient>  $ingredients
     * @param  array<string, Preparation>  $preparations
     */
    /**
     * @return array<string, Menu>
     */
    private function seedMenus(
        Company $company,
        array $menuCategories,
        array $locations,
        array $ingredients,
        array $preparations
    ): array {
        $menuTypes = MenuType::where('company_id', $company->id)->pluck('id', 'name');
        $priorityPerType = [];
        $registered = [];

        $menus = [
            [
                'name' => 'Salade lyonnaise tradition',
                'type' => 'Entrées',
                'service' => MenuServiceType::DIRECT,
                'price' => 12.5,
                'description' => 'Frisée croquante, lardons poêlés, œufs de caille pochés et vinaigrette maison relevée.',
                'categories' => ['tradition lyonnaise'],
                'items' => [
                    ['name' => 'Salade frisée', 'quantity' => 0.08, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réfrigérateur de service'],
                    ['name' => 'Lardons fumés de poitrine', 'quantity' => 0.05, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réfrigérateur de service'],
                    ['name' => 'Œufs de caille', 'quantity' => 3, 'unit' => MeasurementUnit::UNIT, 'location' => 'Réfrigérateur de service'],
                    ['name' => 'Croutons dorés à l’ail', 'quantity' => 0.12, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Cuisine chaude'],
                    ['name' => 'Vinaigrette au vin rouge', 'quantity' => 0.04, 'unit' => MeasurementUnit::LITRE, 'location' => 'Réfrigérateur de service'],
                ],
            ],
            [
                'name' => 'Planche de cochonnailles des Halles',
                'type' => 'Entrées',
                'service' => MenuServiceType::DIRECT,
                'price' => 15.8,
                'description' => 'Sélection de charcuteries lyonnaises servies avec pain de campagne grillé et condiments maison.',
                'categories' => ['tradition lyonnaise', 'menu du marché'],
                'items' => [
                    ['name' => 'Rosette de Lyon artisanale', 'quantity' => 0.12, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réfrigérateur de service'],
                    ['name' => 'Terrine de campagne maison', 'quantity' => 0.1, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réfrigérateur de service'],
                    ['name' => 'Cornichons aigre-doux', 'quantity' => 0.05, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réfrigérateur de service'],
                    ['name' => 'Pain de campagne', 'quantity' => 0.18, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réserve sèche'],
                ],
            ],
            [
                'name' => 'Quenelle de brochet, sauce Nantua',
                'type' => 'Plats',
                'service' => MenuServiceType::PREP,
                'price' => 19.8,
                'description' => 'Quenelles moelleuses servies avec une sauce Nantua généreuse et gratin dauphinois maison.',
                'categories' => ['suggestion du chef'],
                'items' => [
                    ['name' => 'Quenelles de brochet artisanales', 'quantity' => 2, 'unit' => MeasurementUnit::UNIT, 'location' => 'Congélateur'],
                    ['name' => 'Sauce Nantua maison', 'quantity' => 0.18, 'unit' => MeasurementUnit::LITRE, 'location' => 'Cuisine chaude'],
                    ['name' => 'Gratin dauphinois crémeux', 'quantity' => 1, 'unit' => MeasurementUnit::UNIT, 'location' => 'Cuisine chaude'],
                ],
            ],
            [
                'name' => 'Saucisson brioché des gones',
                'type' => 'Plats',
                'service' => MenuServiceType::PREP,
                'price' => 17.4,
                'description' => 'Saucisson lyonnais rôti en brioche, servi tiède avec salade frisée assaisonnée.',
                'categories' => ['tradition lyonnaise'],
                'items' => [
                    ['name' => 'Saucisson à cuire lyonnais', 'quantity' => 0.25, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réfrigérateur de service'],
                    ['name' => 'Pâte briochée maison', 'quantity' => 0.3, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Pâtisserie froide'],
                    ['name' => 'Salade frisée', 'quantity' => 0.06, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Réfrigérateur de service'],
                    ['name' => 'Vinaigrette au vin rouge', 'quantity' => 0.02, 'unit' => MeasurementUnit::LITRE, 'location' => 'Réfrigérateur de service'],
                ],
            ],
            [
                'name' => 'Tarte praline de grand-mère',
                'type' => 'Desserts',
                'service' => MenuServiceType::DIRECT,
                'price' => 8.9,
                'description' => 'Fond sablé croustillant garni d’une crème praline onctueuse et de pralines roses croquantes.',
                'categories' => ['menu du marché'],
                'items' => [
                    ['name' => 'Pâte sablée pur beurre', 'quantity' => 0.18, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Pâtisserie froide'],
                    ['name' => 'Crème praline onctueuse', 'quantity' => 0.12, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Pâtisserie froide'],
                    ['name' => 'Pralines roses concassées', 'quantity' => 0.05, 'unit' => MeasurementUnit::KILOGRAM, 'location' => 'Pâtisserie froide'],
                ],
            ],
            [
                'name' => 'Coca-Cola 33cl',
                'type' => 'Boissons',
                'service' => MenuServiceType::DIRECT,
                'returnable' => true,
                'price' => 3.5,
                'description' => 'Bouteille individuelle de Coca-Cola servie bien fraîche.',
                'categories' => ['boisson'],
                'items' => [
                    ['name' => 'Coca-Cola 33cl', 'quantity' => 1, 'unit' => MeasurementUnit::UNIT, 'location' => 'Réfrigérateur de service'],
                ],
            ],
            [
                'name' => 'Fanta Orange 33cl',
                'type' => 'Boissons',
                'service' => MenuServiceType::DIRECT,
                'returnable' => true,
                'price' => 3.4,
                'description' => 'Canette de Fanta orange 33cl au format individuel.',
                'categories' => ['boisson'],
                'items' => [
                    ['name' => 'Fanta Orange 33cl', 'quantity' => 1, 'unit' => MeasurementUnit::UNIT, 'location' => 'Réfrigérateur de service'],
                ],
            ],
            [
                'name' => 'Thé à la menthe 50cl',
                'type' => 'Boissons',
                'service' => MenuServiceType::DIRECT,
                'price' => 3.2,
                'description' => 'Thé glacé à la menthe 50cl servi bien frais.',
                'categories' => ['boisson'],
                'items' => [
                    ['name' => 'Thé à la menthe 50cl', 'quantity' => 1, 'unit' => MeasurementUnit::UNIT, 'location' => 'Réfrigérateur de service'],
                ],
            ],
        ];

        foreach ($menus as $payload) {
            $menuTypeId = $menuTypes[$payload['type']] ?? null;
            if (! $menuTypeId) {
                $menuType = MenuType::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'name' => $payload['type'],
                    ]
                );

                $menuTypeId = $menuType->id;
                $menuTypes[$payload['type']] = $menuTypeId;
            }

            $priority = $priorityPerType[$menuTypeId] ?? 0;

            $menu = Menu::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $payload['name'],
                ],
                [
                    'menu_type_id' => $menuTypeId,
                    'service_type' => $payload['service']->value,
                    'is_returnable' => (bool) ($payload['returnable'] ?? false),
                    'is_a_la_carte' => true,
                    'public_priority' => $priority,
                    'price' => $payload['price'],
                    'description' => $payload['description'],
                    'image_url' => $this->ensureImagePath('menu', $payload['name']),
                ]
            );

            $priorityPerType[$menuTypeId] = $priority + 1;

            $menu->items()->delete();

            foreach ($payload['items'] as $item) {
                $entity = $ingredients[$item['name']] ?? $preparations[$item['name']] ?? null;
                if (! $entity) {
                    continue;
                }

                $location = $locations[$item['location']] ?? $entity->locations->first();
                if (! $location) {
                    continue;
                }

                MenuItem::create([
                    'menu_id' => $menu->id,
                    'entity_id' => $entity->id,
                    'entity_type' => $entity instanceof Ingredient ? Ingredient::class : Preparation::class,
                    'location_id' => $location->id,
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit']->value,
                ]);
            }

            $categoryIds = collect($payload['categories'] ?? [])
                ->map(fn ($name) => $menuCategories[$name] ?? null)
                ->filter()
                ->values()
                ->all();

            if (! empty($categoryIds)) {
                $menu->categories()->sync($categoryIds);
            }
            $registered[$payload['name']] = $menu->fresh();
        }

        return $registered;
    }

    /**
     * @param  array<string, Ingredient>  $ingredients
     * @param  array<string, Preparation>  $preparations
     */
    private function seedStockMovements(array $ingredients, array $preparations): void
    {
        foreach ($ingredients as $ingredient) {
            $this->seedMovementForTrackable($ingredient);
        }

        foreach ($preparations as $preparation) {
            $this->seedMovementForTrackable($preparation);
        }
    }

    /**
     * @param  array<string, Table>  $tables
     * @param  array<string, Menu>  $menus
     */
    private function seedOrders(Company $company, array $tables, array $menus, User $user): void
    {
        if (empty($tables) || empty($menus)) {
            return;
        }

        $now = Carbon::now();
        $roomTables = collect($tables);

        $payloads = [
            [
                'table' => 'B1',
                'status' => OrderStatus::PAYED,
                'pending_at' => $now->copy()->subDays(3)->setTime(12, 15),
                'served_at' => $now->copy()->subDays(3)->setTime(12, 45),
                'payed_at' => $now->copy()->subDays(3)->setTime(13, 5),
                'steps' => [
                    [
                        'position' => 1,
                        'status' => OrderStepStatus::SERVED,
                        'served_at' => $now->copy()->subDays(3)->setTime(12, 43),
                        'items' => [
                            ['menu' => 'Salade lyonnaise tradition', 'quantity' => 2, 'status' => StepMenuStatus::SERVED],
                            ['menu' => 'Saucisson brioché des gones', 'quantity' => 1, 'status' => StepMenuStatus::SERVED],
                        ],
                    ],
                ],
            ],
            [
                'table' => 'B3',
                'status' => OrderStatus::PAYED,
                'pending_at' => $now->copy()->subDays(1)->setTime(20, 5),
                'served_at' => $now->copy()->subDays(1)->setTime(20, 45),
                'payed_at' => $now->copy()->subDays(1)->setTime(21, 2),
                'steps' => [
                    [
                        'position' => 1,
                        'status' => OrderStepStatus::SERVED,
                        'served_at' => $now->copy()->subDays(1)->setTime(20, 40),
                        'items' => [
                            ['menu' => 'Planche de cochonnailles des Halles', 'quantity' => 1, 'status' => StepMenuStatus::SERVED],
                        ],
                    ],
                    [
                        'position' => 2,
                        'status' => OrderStepStatus::SERVED,
                        'served_at' => $now->copy()->subDays(1)->setTime(20, 50),
                        'items' => [
                            ['menu' => 'Quenelle de brochet, sauce Nantua', 'quantity' => 2, 'status' => StepMenuStatus::SERVED],
                        ],
                    ],
                    [
                        'position' => 3,
                        'status' => OrderStepStatus::SERVED,
                        'served_at' => $now->copy()->subDays(1)->setTime(20, 55),
                        'items' => [
                            ['menu' => 'Tarte praline de grand-mère', 'quantity' => 2, 'status' => StepMenuStatus::SERVED],
                        ],
                    ],
                ],
            ],
            [
                'table' => 'B2',
                'status' => OrderStatus::CANCELED,
                'pending_at' => $now->copy()->subDays(2)->setTime(13, 10),
                'canceled_at' => $now->copy()->subDays(2)->setTime(13, 30),
                'steps' => [
                    [
                        'position' => 1,
                        'status' => OrderStepStatus::IN_PREP,
                        'items' => [
                            ['menu' => 'Salade lyonnaise tradition', 'quantity' => 1, 'status' => StepMenuStatus::IN_PREP],
                        ],
                    ],
                ],
            ],
            [
                'table' => 'B4',
                'status' => OrderStatus::PENDING,
                'pending_at' => $now->copy()->setTime(11, 45),
                'steps' => [
                    [
                        'position' => 1,
                        'status' => OrderStepStatus::IN_PREP,
                        'items' => [
                            ['menu' => 'Quenelle de brochet, sauce Nantua', 'quantity' => 1, 'status' => StepMenuStatus::IN_PREP],
                        ],
                    ],
                ],
            ],
            [
                'table' => 'B1',
                'status' => OrderStatus::SERVED,
                'pending_at' => $now->copy()->subHours(5),
                'served_at' => $now->copy()->subHours(4)->addMinutes(10),
                'steps' => [
                    [
                        'position' => 1,
                        'status' => OrderStepStatus::SERVED,
                        'served_at' => $now->copy()->subHours(4)->addMinutes(5),
                        'items' => [
                            ['menu' => 'Planche de cochonnailles des Halles', 'quantity' => 1, 'status' => StepMenuStatus::SERVED],
                            ['menu' => 'Tarte praline de grand-mère', 'quantity' => 1, 'status' => StepMenuStatus::SERVED],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($payloads as $data) {
            $table = $roomTables->get($data['table']) ?? $roomTables->first();

            if (! $table) {
                continue;
            }

            $order = Order::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'table_id' => $table->id,
                    'pending_at' => $data['pending_at'],
                ],
                [
                    'user_id' => $user->id,
                    'status' => $data['status']->value,
                    'served_at' => $data['served_at'] ?? null,
                    'payed_at' => $data['payed_at'] ?? null,
                    'canceled_at' => $data['canceled_at'] ?? null,
                ]
            );

            $order->steps()->delete();

            foreach ($data['steps'] as $stepPayload) {
                $step = OrderStep::create([
                    'order_id' => $order->id,
                    'position' => $stepPayload['position'],
                    'status' => $stepPayload['status']->value,
                    'served_at' => $stepPayload['served_at'] ?? null,
                ]);

                foreach ($stepPayload['items'] as $item) {
                    $menu = $menus[$item['menu']] ?? null;

                    if (! $menu) {
                        continue;
                    }

                    StepMenu::create([
                        'order_step_id' => $step->id,
                        'menu_id' => $menu->id,
                        'quantity' => $item['quantity'],
                        'status' => $item['status']->value,
                    ]);
                }
            }

            $order->histories()->delete();
            $this->seedOrderHistory($order, $user);
        }
    }

    private function seedOrderHistory(Order $order, User $user): void
    {
        $events = [
            ['action' => 'order_created', 'timestamp' => $order->pending_at ?? $order->created_at, 'status' => OrderStatus::PENDING],
        ];

        if ($order->served_at) {
            $events[] = ['action' => 'order_served', 'timestamp' => $order->served_at, 'status' => OrderStatus::SERVED];
        }

        if ($order->payed_at) {
            $events[] = ['action' => 'order_payed', 'timestamp' => $order->payed_at, 'status' => OrderStatus::PAYED];
        }

        if ($order->canceled_at) {
            $events[] = ['action' => 'order_canceled', 'timestamp' => $order->canceled_at, 'status' => OrderStatus::CANCELED];
        }

        foreach ($events as $event) {
            if (! $event['timestamp']) {
                continue;
            }

            $status = $event['status'] ?? $order->status;
            $statusValue = $status instanceof OrderStatus ? $status->value : (string) $status;

            $history = OrderHistory::create([
                'order_id' => $order->id,
                'company_id' => $order->company_id,
                'user_id' => $user->id,
                'action' => $event['action'],
                'payload' => ['status' => $statusValue],
            ]);

            $history->forceFill([
                'created_at' => $event['timestamp'],
                'updated_at' => $event['timestamp'],
            ])->saveQuietly();
        }
    }

    /**
     * @param  Ingredient|Preparation  $trackable
     */
    private function seedMovementForTrackable($trackable): void
    {
        $trackable->loadMissing('locations');

        foreach ($trackable->locations as $location) {
            $current = (float) ($location->pivot->quantity ?? 0);
            if ($current <= 0.01) {
                continue;
            }

            $alreadySeeded = StockMovement::query()
                ->where('trackable_id', $trackable->id)
                ->where('trackable_type', $trackable::class)
                ->where('location_id', $location->id)
                ->exists();

            if ($alreadySeeded) {
                continue;
            }

            $baseDate = Carbon::now()->subDays(12);
            $initial = round(max($current * 0.4, 0.5), 2);
            $firstAddition = round(max($current * 0.25, 0.2), 2);
            $firstAfter = round($initial + $firstAddition, 2);

            $this->recordMovement($trackable, $location->id, 'addition', 0.0, $initial, $baseDate->copy(), 'Réception fournisseur');
            $this->recordMovement($trackable, $location->id, 'addition', $initial, $firstAfter, $baseDate->copy()->addDays(2), 'Mise en production');

            $withdrawQuantity = round(min($firstAfter * 0.2, $firstAfter), 2);
            $afterWithdrawal = round(max($firstAfter - $withdrawQuantity, 0), 2);
            $this->recordMovement($trackable, $location->id, 'withdrawal', $firstAfter, $afterWithdrawal, $baseDate->copy()->addDays(5), 'Service salle');

            if (abs($afterWithdrawal - $current) > 0.01) {
                $type = $current >= $afterWithdrawal ? 'addition' : 'withdrawal';

                $this->recordMovement(
                    $trackable,
                    $location->id,
                    $type,
                    $afterWithdrawal,
                    $current,
                    $baseDate->copy()->addDays(8),
                    $type === 'addition' ? 'Complément production' : 'Ajustement inventaire'
                );
            }
        }
    }

    /**
     * @param  Ingredient|Preparation  $trackable
     */
    private function recordMovement($trackable, int $locationId, string $type, float $before, float $after, Carbon $date, ?string $reason = null): void
    {
        $quantity = round(abs($after - $before), 2);

        if ($quantity <= 0.0) {
            return;
        }

        $movement = StockMovement::query()->create([
            'trackable_id' => $trackable->id,
            'trackable_type' => $trackable::class,
            'location_id' => $locationId,
            'company_id' => $trackable->company_id,
            'user_id' => null,
            'type' => $type,
            'reason' => $reason,
            'quantity' => $quantity,
            'quantity_before' => round($before, 2),
            'quantity_after' => round($after, 2),
        ]);

        $movement->forceFill([
            'created_at' => $date,
            'updated_at' => $date,
        ])->saveQuietly();
    }

    private function ensureImagePath(string $type, string $name): string
    {
        if (isset($this->imageCache[$type][$name])) {
            return $this->imageCache[$type][$name];
        }

        $folder = match ($type) {
            'ingredient' => 'seeders/ingredients',
            'preparation' => 'seeders/preparations',
            'menu' => 'seeders/menus',
            default => 'seeders/others',
        };

        $url = self::IMAGE_MAP[$type][$name] ?? self::FALLBACK_IMAGE_URL;
        // $this->command->getOutput()->writeln("Fetching image for {$type} '{$name}' from URL: {$url}");

        try {
            if ($url) {
                $path = $this->imageService->storeFromUrl($url, $folder, self::IMAGE_MAX_BYTES);
                // $this->command->getOutput()->writeln("  -> Stored at path: {$path}");
            } else {
                $path = $this->imageService->storePlaceholder();
                // $this->command->getOutput()->writeln("  -> No URL provided, using placeholder image at path: {$path}");
            }
        } catch (Throwable $e) {
            $path = $this->imageService->storePlaceholder();
            $this->command->getOutput()->writeln("  -> Failed to fetch image, using placeholder image at path: {$path}");
            $this->command->getOutput()->writeln("     Error: {$e->getMessage()}");
        }

        return $this->imageCache[$type][$name] = $path;
    }
}
