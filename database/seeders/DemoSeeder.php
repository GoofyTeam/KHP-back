<?php

namespace Database\Seeders;

use App\Enums\MeasurementUnit;
use App\Models\Category;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Preparation;
use App\Models\User;
use App\Services\ImageService;
use App\Services\OpenFoodFactsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
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
                'preparations' => [
                    [
                        'name' => 'Pâté en croûte',
                        'quantity' => 1,
                        'unit' => MeasurementUnit::UNIT,
                    ],
                    [
                        'name' => 'Pickles de légumes',
                        'quantity' => 1,
                        'unit' => MeasurementUnit::UNIT,
                    ],
                ],
            ],
            [
                'name' => 'Foie gras de canard, brioche parisienne',
                'price' => 32.0,
                'ingredients' => [
                    [
                        'name' => 'Foie gras de canard cru',
                        'quantity' => 1,
                        'unit' => MeasurementUnit::UNIT,
                    ],
                ],
                'preparations' => [
                    [
                        'name' => 'Brioche parisienne',
                        'quantity' => 1,
                        'unit' => MeasurementUnit::UNIT,
                    ],
                ],
            ],
            [
                'name' => 'Homard bleu rafraîchi, haricots verts et amandes fraîches',
                'price' => 38.0,
                'ingredients' => [
                    [
                        'name' => 'Homard bleu',
                        'quantity' => 1,
                        'unit' => MeasurementUnit::UNIT,
                    ],
                    [
                        'name' => 'Haricots verts frais',
                        'quantity' => 200,
                        'unit' => MeasurementUnit::GRAM,
                    ],
                    [
                        'name' => 'Amandes fraîches',
                        'quantity' => 40,
                        'unit' => MeasurementUnit::GRAM,
                    ],
                    [
                        'name' => 'Huile d’olive',
                        'quantity' => 25,
                        'unit' => MeasurementUnit::MILLILITRE,
                    ],
                    [
                        'name' => 'Citron',
                        'quantity' => 1,
                        'unit' => MeasurementUnit::UNIT,
                    ],
                    [
                        'name' => 'Sel',
                        'quantity' => 2,
                        'unit' => MeasurementUnit::GRAM,
                    ],
                    [
                        'name' => 'Poivre',
                        'quantity' => 1,
                        'unit' => MeasurementUnit::GRAM,
                    ],
                ],
                'preparations' => [],
            ],
            [
                'name' => 'Tomate de plein champs fondante, anchois et basilic',
                'price' => 30.0,
                'ingredients' => [
                    [
                        'name' => 'Tomate de plein champ',
                        'quantity' => 1,
                        'unit' => MeasurementUnit::UNIT,
                    ],
                    [
                        'name' => 'Filets d’anchois',
                        'quantity' => 3,
                        'unit' => MeasurementUnit::UNIT,
                    ],
                    [
                        'name' => 'Basilic frais',
                        'quantity' => 15,
                        'unit' => MeasurementUnit::GRAM,
                    ],
                    [
                        'name' => 'Huile d’olive',
                        'quantity' => 20,
                        'unit' => MeasurementUnit::MILLILITRE,
                    ],
                ],
                'preparations' => [],
            ],
        ],
        'plats' => [
            [
                'name' => 'Dos de bar doré, courgette trompette et jus d’une marinière',
                'price' => 38.0,
                'ingredients' => [
                    [
                        'name' => 'Dos de bar',
                        'quantity' => 1,
                        'unit' => MeasurementUnit::UNIT,
                    ],
                    [
                        'name' => 'Courgette trompette',
                        'quantity' => 1,
                        'unit' => MeasurementUnit::UNIT,
                    ],
                ],
                'preparations' => [
                    [
                        'name' => 'Jus de marinière',
                        'quantity' => 1,
                        'unit' => MeasurementUnit::UNIT,
                    ],
                ],
            ],
            [
                'name' => 'Sole à la meunière, cassolette d’artichauts (pour deux)',
                'price' => 160.0,
                'ingredients' => [],
                'preparations' => [
                    [
                        'name' => 'Sole à la meunière',
                        'quantity' => 1,
                        'unit' => MeasurementUnit::UNIT,
                    ],
                    [
                        'name' => 'Cassolette d’artichauts',
                        'quantity' => 1,
                        'unit' => MeasurementUnit::UNIT,
                    ],
                ],
            ],
        ],
        'fromage' => [
            [
                'name' => 'Fromages de France',
                'price' => 16.0,
                'ingredients' => [
                    [
                        'name' => 'Sélection de fromages de vache, chèvre, brebis',
                        'quantity' => 1,
                        'unit' => MeasurementUnit::UNIT,
                    ],
                ],
                'preparations' => [],
            ],
        ],
        'desserts' => [
            [
                'name' => 'Millefeuille classique à la vanille',
                'price' => 14.0,
                'ingredients' => [],
                'preparations' => [
                    [
                        'name' => 'Millefeuille',
                        'quantity' => 1,
                        'unit' => MeasurementUnit::UNIT,
                    ],
                ],
            ],
            [
                'name' => 'Pêche Melba',
                'price' => 14.0,
                'ingredients' => [
                    [
                        'name' => 'Glace vanille',
                        'quantity' => 2,
                        'unit' => MeasurementUnit::UNIT,
                    ],
                ],
                'preparations' => [
                    [
                        'name' => 'Pêches pochées',
                        'quantity' => 1,
                        'unit' => MeasurementUnit::UNIT,
                    ],
                    [
                        'name' => 'Coulis de framboise',
                        'quantity' => 1,
                        'unit' => MeasurementUnit::UNIT,
                    ],
                ],
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
            [
                'ingredient' => 'Farine',
                'quantity' => 500,
                'unit' => MeasurementUnit::GRAM,
            ],
            [
                'ingredient' => 'Beurre',
                'quantity' => 250,
                'unit' => MeasurementUnit::GRAM,
            ],
            [
                'ingredient' => 'Eau',
                'quantity' => 120,
                'unit' => MeasurementUnit::MILLILITRE,
            ],
            [
                'ingredient' => 'Sel',
                'quantity' => 10,
                'unit' => MeasurementUnit::GRAM,
            ],
        ],
        'Farce de porc maison' => [
            [
                'ingredient' => 'Échine de porc',
                'quantity' => 4,
                'unit' => MeasurementUnit::UNIT,
            ],
            [
                'ingredient' => 'Veau',
                'quantity' => 3,
                'unit' => MeasurementUnit::UNIT,
            ],
            [
                'ingredient' => 'Foie de volaille',
                'quantity' => 4,
                'unit' => MeasurementUnit::UNIT,
            ],
            [
                'ingredient' => 'Œufs',
                'quantity' => 2,
                'unit' => MeasurementUnit::UNIT,
            ],
            [
                'ingredient' => 'Crème',
                'quantity' => 200,
                'unit' => MeasurementUnit::MILLILITRE,
            ],
            [
                'ingredient' => 'Sel',
                'quantity' => 12,
                'unit' => MeasurementUnit::GRAM,
            ],
            [
                'ingredient' => 'Poivre',
                'quantity' => 6,
                'unit' => MeasurementUnit::GRAM,
            ],
            [
                'ingredient' => 'Armagnac',
                'quantity' => 30,
                'unit' => MeasurementUnit::MILLILITRE,
            ],
            [
                'ingredient' => 'Épices',
                'quantity' => 5,
                'unit' => MeasurementUnit::GRAM,
            ],
        ],
        'Gelée' => [
            [
                'ingredient' => 'Fond de volaille',
                'quantity' => 600,
                'unit' => MeasurementUnit::MILLILITRE,
            ],
            [
                'ingredient' => 'Gélatine',
                'quantity' => 20,
                'unit' => MeasurementUnit::GRAM,
            ],
        ],
        'Pâté en croûte' => [
            [
                'preparation' => 'Pâte brisée',
                'quantity' => 1,
                'unit' => MeasurementUnit::UNIT,
            ],
            [
                'preparation' => 'Farce de porc maison',
                'quantity' => 1,
                'unit' => MeasurementUnit::UNIT,
            ],
            [
                'preparation' => 'Gelée',
                'quantity' => 1,
                'unit' => MeasurementUnit::UNIT,
            ],
        ],
        'Marinade' => [
            [
                'ingredient' => 'Vinaigre blanc',
                'quantity' => 300,
                'unit' => MeasurementUnit::MILLILITRE,
            ],
            [
                'ingredient' => 'Eau',
                'quantity' => 200,
                'unit' => MeasurementUnit::MILLILITRE,
            ],
            [
                'ingredient' => 'Sucre',
                'quantity' => 80,
                'unit' => MeasurementUnit::GRAM,
            ],
            [
                'ingredient' => 'Sel',
                'quantity' => 20,
                'unit' => MeasurementUnit::GRAM,
            ],
            [
                'ingredient' => 'Graines de moutarde',
                'quantity' => 15,
                'unit' => MeasurementUnit::GRAM,
            ],
            [
                'ingredient' => 'Poivre',
                'quantity' => 8,
                'unit' => MeasurementUnit::GRAM,
            ],
        ],
        'Pickles de légumes' => [
            [
                'ingredient' => 'Carottes',
                'quantity' => 1.5,
                'unit' => MeasurementUnit::KILOGRAM,
            ],
            [
                'ingredient' => 'Chou-fleur',
                'quantity' => 3,
                'unit' => MeasurementUnit::UNIT,
            ],
            [
                'ingredient' => 'Oignons',
                'quantity' => 1,
                'unit' => MeasurementUnit::KILOGRAM,
            ],
            [
                'ingredient' => 'Cornichons',
                'quantity' => 0.8,
                'unit' => MeasurementUnit::KILOGRAM,
            ],
            [
                'preparation' => 'Marinade',
                'quantity' => 1,
                'unit' => MeasurementUnit::UNIT,
            ],
        ],
        'Brioche parisienne' => [
            [
                'ingredient' => 'Farine',
                'quantity' => 1.2,
                'unit' => MeasurementUnit::KILOGRAM,
            ],
            [
                'ingredient' => 'Œufs',
                'quantity' => 8,
                'unit' => MeasurementUnit::UNIT,
            ],
            [
                'ingredient' => 'Beurre',
                'quantity' => 0.5,
                'unit' => MeasurementUnit::KILOGRAM,
            ],
            [
                'ingredient' => 'Lait',
                'quantity' => 500,
                'unit' => MeasurementUnit::MILLILITRE,
            ],
            [
                'ingredient' => 'Sucre',
                'quantity' => 0.2,
                'unit' => MeasurementUnit::KILOGRAM,
            ],
            [
                'ingredient' => 'Levure de boulanger',
                'quantity' => 40,
                'unit' => MeasurementUnit::GRAM,
            ],
            [
                'ingredient' => 'Sel',
                'quantity' => 15,
                'unit' => MeasurementUnit::GRAM,
            ],
        ],
        'Jus de marinière' => [
            [
                'ingredient' => 'Vin blanc sec',
                'quantity' => 0.6,
                'unit' => MeasurementUnit::LITRE,
            ],
            [
                'ingredient' => 'Échalotes',
                'quantity' => 0.4,
                'unit' => MeasurementUnit::KILOGRAM,
            ],
            [
                'ingredient' => 'Beurre',
                'quantity' => 0.2,
                'unit' => MeasurementUnit::KILOGRAM,
            ],
            [
                'ingredient' => 'Persil',
                'quantity' => 80,
                'unit' => MeasurementUnit::GRAM,
            ],
            [
                'ingredient' => 'Sel',
                'quantity' => 10,
                'unit' => MeasurementUnit::GRAM,
            ],
            [
                'ingredient' => 'Poivre',
                'quantity' => 5,
                'unit' => MeasurementUnit::GRAM,
            ],
        ],
        'Sole à la meunière' => [
            [
                'ingredient' => 'Sole',
                'quantity' => 2,
                'unit' => MeasurementUnit::UNIT,
            ],
            [
                'ingredient' => 'Beurre',
                'quantity' => 0.18,
                'unit' => MeasurementUnit::KILOGRAM,
            ],
            [
                'ingredient' => 'Farine',
                'quantity' => 0.25,
                'unit' => MeasurementUnit::KILOGRAM,
            ],
            [
                'ingredient' => 'Jus de citron',
                'quantity' => 120,
                'unit' => MeasurementUnit::MILLILITRE,
            ],
            [
                'ingredient' => 'Persil',
                'quantity' => 60,
                'unit' => MeasurementUnit::GRAM,
            ],
        ],
        'Cassolette d’artichauts' => [
            [
                'ingredient' => 'Artichauts frais',
                'quantity' => 4,
                'unit' => MeasurementUnit::UNIT,
            ],
            [
                'ingredient' => 'Fond de volaille',
                'quantity' => 800,
                'unit' => MeasurementUnit::MILLILITRE,
            ],
            [
                'ingredient' => 'Huile d’olive',
                'quantity' => 120,
                'unit' => MeasurementUnit::MILLILITRE,
            ],
            [
                'ingredient' => 'Ail',
                'quantity' => 6,
                'unit' => MeasurementUnit::UNIT,
            ],
            [
                'ingredient' => 'Sel',
                'quantity' => 8,
                'unit' => MeasurementUnit::GRAM,
            ],
            [
                'ingredient' => 'Poivre',
                'quantity' => 4,
                'unit' => MeasurementUnit::GRAM,
            ],
        ],
        'Pâte feuilletée' => [
            [
                'ingredient' => 'Farine',
                'quantity' => 1.5,
                'unit' => MeasurementUnit::KILOGRAM,
            ],
            [
                'ingredient' => 'Beurre',
                'quantity' => 1,
                'unit' => MeasurementUnit::KILOGRAM,
            ],
            [
                'ingredient' => 'Eau',
                'quantity' => 600,
                'unit' => MeasurementUnit::MILLILITRE,
            ],
            [
                'ingredient' => 'Sel',
                'quantity' => 15,
                'unit' => MeasurementUnit::GRAM,
            ],
        ],
        'Crème pâtissière à la vanille' => [
            [
                'ingredient' => 'Lait',
                'quantity' => 2000,
                'unit' => MeasurementUnit::MILLILITRE,
            ],
            [
                'ingredient' => 'Sucre',
                'quantity' => 0.35,
                'unit' => MeasurementUnit::KILOGRAM,
            ],
            [
                'ingredient' => 'Jaunes d’œuf',
                'quantity' => 18,
                'unit' => MeasurementUnit::UNIT,
            ],
            [
                'ingredient' => 'Fécule',
                'quantity' => 0.12,
                'unit' => MeasurementUnit::KILOGRAM,
            ],
            [
                'ingredient' => 'Gousse de vanille',
                'quantity' => 6,
                'unit' => MeasurementUnit::UNIT,
            ],
        ],
        'Millefeuille' => [
            [
                'preparation' => 'Pâte feuilletée',
                'quantity' => 1,
                'unit' => MeasurementUnit::UNIT,
            ],
            [
                'preparation' => 'Crème pâtissière à la vanille',
                'quantity' => 1,
                'unit' => MeasurementUnit::UNIT,
            ],
            [
                'ingredient' => 'Sucre glace',
                'quantity' => 0.25,
                'unit' => MeasurementUnit::KILOGRAM,
            ],
        ],
        'Pêches pochées' => [
            [
                'ingredient' => 'Pêches',
                'quantity' => 12,
                'unit' => MeasurementUnit::UNIT,
            ],
            [
                'ingredient' => 'Sirop',
                'quantity' => 1500,
                'unit' => MeasurementUnit::MILLILITRE,
            ],
            [
                'ingredient' => 'Vanille',
                'quantity' => 25,
                'unit' => MeasurementUnit::GRAM,
            ],
        ],
        'Coulis de framboise' => [
            [
                'ingredient' => 'Framboises',
                'quantity' => 1.2,
                'unit' => MeasurementUnit::KILOGRAM,
            ],
            [
                'ingredient' => 'Sucre',
                'quantity' => 0.3,
                'unit' => MeasurementUnit::KILOGRAM,
            ],
        ],
        'Pêche Melba' => [
            [
                'preparation' => 'Pêches pochées',
                'quantity' => 1,
                'unit' => MeasurementUnit::UNIT,
            ],
            [
                'preparation' => 'Coulis de framboise',
                'quantity' => 1,
                'unit' => MeasurementUnit::UNIT,
            ],
            [
                'ingredient' => 'Glace vanille',
                'quantity' => 1.5,
                'unit' => MeasurementUnit::LITRE,
            ],
        ],
    ];

    private const INGREDIENTS = [
        'Farine' => [
            'category' => 'Farines',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 1000,
            'stock' => 5000,
            'barcode' => '4056489565536',
        ],
        'Beurre' => [
            'category' => 'Produits Laitiers',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 250,
            'stock' => 1500,
            'barcode' => '26064413',
        ],
        'Eau' => [
            'category' => 'Boissons',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 1000,
            'stock' => 50,
            'barcode' => '1234500001857',
        ],
        'Sel' => [
            'category' => 'Épicerie',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 1000,
            'stock' => 2000,
            'barcode' => '10020811',
        ],
        'Échine de porc' => [
            'category' => 'Viandes',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 180,
            'stock' => 24,
            'barcode' => '0207024022173',
        ],
        'Veau' => [
            'category' => 'Viandes',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 160,
            'stock' => 18,
            'barcode' => '2695314012009',
        ],
        'Foie de volaille' => [
            'category' => 'Viandes',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 90,
            'stock' => 40,
            'barcode' => '0215085018561',
        ],
        'Œufs' => [
            'category' => 'Œufs',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::UNIT,
            'base_quantity' => 1,
            'stock' => 180,
            'barcode' => '3560070432080',
        ],
        'Crème' => [
            'category' => 'Produits Laitiers',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 1000,
            'stock' => 18,
            'barcode' => '3258561419299',
        ],
        'Poivre' => [
            'category' => 'Épices',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 250,
            'stock' => 600,
            'barcode' => '8720254531779',
        ],
        'Armagnac' => [
            'category' => 'Spiritueux',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 700,
            'stock' => 8,
            'barcode' => '3560070575480',
        ],
        'Épices' => [
            'category' => 'Épices',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 200,
            'stock' => 400,
            'barcode' => '3700483800544',
        ],
        'Fond de volaille' => [
            'category' => 'Épicerie',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 1000,
            'stock' => 15,
            'barcode' => '3256225451647',
        ],
        'Gélatine' => [
            'category' => 'Épicerie',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 200,
            'stock' => 300,
            'barcode' => '3256225731978',
        ],
        'Carottes' => [
            'category' => 'Légumes',
            'unit' => MeasurementUnit::KILOGRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 1000,
            'stock' => 12,
            'barcode' => '3596710431151',
        ],
        'Chou-fleur' => [
            'category' => 'Légumes',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 900,
            'stock' => 18,
            'barcode' => '3560070122349',
        ],
        'Oignons' => [
            'category' => 'Légumes',
            'unit' => MeasurementUnit::KILOGRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 1000,
            'stock' => 10,
            'barcode' => '3363290420116',
        ],
        'Cornichons' => [
            'category' => 'Épicerie',
            'unit' => MeasurementUnit::KILOGRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 500,
            'stock' => 6,
            'barcode' => '4061464817722',
        ],
        'Vinaigre blanc' => [
            'category' => 'Épicerie',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 1000,
            'stock' => 20,
            'barcode' => '3077311522405',
        ],
        'Sucre' => [
            'category' => 'Épicerie',
            'unit' => MeasurementUnit::KILOGRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 1000,
            'stock' => 18,
            'barcode' => '3596710473557',
        ],
        'Graines de moutarde' => [
            'category' => 'Épices',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 200,
            'stock' => 350,
            'barcode' => '7610845400434',
        ],
        'Foie gras de canard cru' => [
            'category' => 'Viandes',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 500,
            'stock' => 0,
            'barcode' => '26078410',
        ],
        'Lait' => [
            'category' => 'Produits Laitiers',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 1000,
            'stock' => 30,
            'barcode' => '3428272970017',
        ],
        'Levure de boulanger' => [
            'category' => 'Épicerie',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 100,
            'stock' => 150,
            'barcode' => '2006050036622',
        ],
        'Homard bleu' => [
            'category' => 'Fruits de Mer',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 600,
            'stock' => 0,
            'barcode' => '3770000648317',
        ],
        'Haricots verts frais' => [
            'category' => 'Légumes',
            'unit' => MeasurementUnit::KILOGRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 1000,
            'stock' => 14,
            'barcode' => '3760086270076',
        ],
        'Amandes fraîches' => [
            'category' => 'Fruits secs',
            'unit' => MeasurementUnit::KILOGRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 500,
            'stock' => 8,
            'barcode' => '3700194630287',
        ],
        'Huile d’olive' => [
            'category' => 'Épicerie',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 1000,
            'stock' => 25,
            'barcode' => '3424096003078',
        ],
        'Citron' => [
            'category' => 'Fruits',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 120,
            'stock' => 45,
            'barcode' => '3256226081881',
        ],
        'Tomate de plein champ' => [
            'category' => 'Légumes',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 220,
            'stock' => 60,
            'barcode' => '3017800246658',
        ],
        'Filets d’anchois' => [
            'category' => 'Poissons',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 500,
            'stock' => 750,
            'barcode' => '3218370591821',
        ],
        'Basilic frais' => [
            'category' => 'Herbes aromatiques',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 200,
            'stock' => 300,
            'barcode' => '3411061111029',
        ],
        'Dos de bar' => [
            'category' => 'Poissons',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 280,
            'stock' => 16,
            'barcode' => '3664335055264',
        ],
        'Courgette trompette' => [
            'category' => 'Légumes',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 200,
            'stock' => 32,
            'barcode' => '2306375001603',
        ],
        'Vin blanc sec' => [
            'category' => 'Boissons',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 750,
            'stock' => 24,
            'barcode' => '3660989151932',
        ],
        'Échalotes' => [
            'category' => 'Légumes',
            'unit' => MeasurementUnit::KILOGRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 1000,
            'stock' => 8,
            'barcode' => '8431876150353',
        ],
        'Persil' => [
            'category' => 'Herbes aromatiques',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 200,
            'stock' => 300,
            'barcode' => '2006050101283',
        ],
        'Sole' => [
            'category' => 'Poissons',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 350,
            'stock' => 10,
            'barcode' => '0059749982474',
        ],
        'Jus de citron' => [
            'category' => 'Épicerie',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 1000,
            'stock' => 12,
            'barcode' => '3564700299043',
        ],
        'Artichauts frais' => [
            'category' => 'Légumes',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 320,
            'stock' => 24,
            'barcode' => '3256220652766',
        ],
        'Ail' => [
            'category' => 'Légumes',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 60,
            'stock' => 50,
            'barcode' => '3256228100191',
        ],
        'Sucre glace' => [
            'category' => 'Épicerie',
            'unit' => MeasurementUnit::KILOGRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 1000,
            'stock' => 6,
            'barcode' => '3220035730001',
        ],
        'Gousse de vanille' => [
            'category' => 'Épices',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 6,
            'stock' => 80,
            'barcode' => '3256225732043',
        ],
        'Jaunes d’œuf' => [
            'category' => 'Œufs',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::UNIT,
            'base_quantity' => 1,
            'stock' => 200,
            'barcode' => '3439496001838',
        ],
        'Fécule' => [
            'category' => 'Farines',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 500,
            'stock' => 600,
            'barcode' => '3347431805482',
        ],
        'Pêches' => [
            'category' => 'Fruits',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 180,
            'stock' => 40,
            'barcode' => '3276559409466',
        ],
        'Sirop' => [
            'category' => 'Épicerie',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 1000,
            'stock' => 18,
            'barcode' => '5708776000877',
        ],
        'Vanille' => [
            'category' => 'Épices',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 100,
            'stock' => 250,
            'barcode' => '6133798001790',
        ],
        'Framboises' => [
            'category' => 'Fruits',
            'unit' => MeasurementUnit::KILOGRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 1000,
            'stock' => 12,
            'barcode' => '3385630118309',
        ],
        'Glace vanille' => [
            'category' => 'Desserts',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 1000,
            'stock' => 14,
            'barcode' => '26048154',
        ],
        'Sélection de fromages de vache, chèvre, brebis' => [
            'category' => 'Fromages',
            'unit' => MeasurementUnit::KILOGRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 1500,
            'stock' => 9,
            'barcode' => '0200340018370',
        ],
    ];

    private const TEMP_IMAGE_FOLDER = 'tmp/demo-seeder/ingredients';

    private const DEFAULT_PLACEHOLDER_SOURCE = 'private/images/placeholder.svg';

    private const DEMO_PLACEHOLDER_PATH = 'tmp/demo-seeder/placeholder.svg';

    private const DEFAULT_LOCATION_NAME = 'Chambre froide Maison Gustave';

    private const PREPARATION_LOCATION_NAME = 'Laboratoire pâtisserie Maison Gustave';

    private const LOCATION_BLUEPRINTS = [
        self::DEFAULT_LOCATION_NAME => [
            'type' => 'Réfrigérateur',
            'aliases' => ['Réfrigérateur'],
        ],
        'Congélateur Maison Gustave' => [
            'type' => 'Congélateur',
            'aliases' => ['Congélateur'],
        ],
        'Réserve sèche Maison Gustave' => [
            'type' => 'Autre',
        ],
        'Cave à vins Maison Gustave' => [
            'type' => 'Autre',
        ],
        self::PREPARATION_LOCATION_NAME => [
            'type' => 'Autre',
        ],
    ];

    private const CATEGORY_SHELF_LIFE = [
        'Farines' => ['Autre' => 720],
        'Produits Laitiers' => ['Réfrigérateur' => 120, 'Congélateur' => 360],
        'Boissons' => ['Autre' => 720, 'Réfrigérateur' => 120],
        'Épicerie' => ['Autre' => 720],
        'Viandes' => ['Réfrigérateur' => 72, 'Congélateur' => 720],
        'Œufs' => ['Réfrigérateur' => 240],
        'Épices' => ['Autre' => 1440],
        'Spiritueux' => ['Autre' => 2880],
        'Fruits de Mer' => ['Réfrigérateur' => 48, 'Congélateur' => 240],
        'Légumes' => ['Réfrigérateur' => 168],
        'Fruits' => ['Réfrigérateur' => 120],
        'Fruits secs' => ['Autre' => 720],
        'Poissons' => ['Réfrigérateur' => 48, 'Congélateur' => 240],
        'Herbes aromatiques' => ['Réfrigérateur' => 72],
        'Fromages' => ['Réfrigérateur' => 168],
        'Desserts' => ['Congélateur' => 240, 'Réfrigérateur' => 72],
        'Préparations Maison' => ['Réfrigérateur' => 48, 'Congélateur' => 168],
        'Ingrédients Divers' => ['Autre' => 336, 'Réfrigérateur' => 96],
    ];

    private const DEFAULT_CATEGORY_SHELF_LIFE = [
        'Réfrigérateur' => 96,
        'Congélateur' => 360,
        'Autre' => 720,
    ];

    private const CATEGORY_LOCATION_MAP = [
        'Farines' => 'Réserve sèche Maison Gustave',
        'Épicerie' => 'Réserve sèche Maison Gustave',
        'Épices' => 'Réserve sèche Maison Gustave',
        'Fruits secs' => 'Réserve sèche Maison Gustave',
        'Boissons' => 'Cave à vins Maison Gustave',
        'Spiritueux' => 'Cave à vins Maison Gustave',
        'Desserts' => 'Congélateur Maison Gustave',
        'Produits Laitiers' => self::DEFAULT_LOCATION_NAME,
        'Viandes' => self::DEFAULT_LOCATION_NAME,
        'Fruits de Mer' => self::DEFAULT_LOCATION_NAME,
        'Poissons' => self::DEFAULT_LOCATION_NAME,
        'Légumes' => self::DEFAULT_LOCATION_NAME,
        'Fruits' => self::DEFAULT_LOCATION_NAME,
        'Herbes aromatiques' => self::DEFAULT_LOCATION_NAME,
        'Fromages' => self::DEFAULT_LOCATION_NAME,
        'Œufs' => self::DEFAULT_LOCATION_NAME,
        'Ingrédients Divers' => 'Réserve sèche Maison Gustave',
    ];

    private const INGREDIENT_LOCATION_OVERRIDES = [
        'Glace vanille' => 'Congélateur Maison Gustave',
    ];

    private const PREPARATION_STOCK_LEVELS = [
        'Pâté en croûte' => 24,
        'Pickles de légumes' => 30,
        'Brioche parisienne' => 36,
        'Jus de marinière' => 40,
        'Sole à la meunière' => 0,
        'Cassolette d’artichauts' => 18,
        'Millefeuille' => 30,
        'Pêches pochées' => 24,
        'Coulis de framboise' => 24,
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

    private ?string $placeholderImagePath = null;

    /** @var array<string, int> */
    private array $ingredientLocations = [];

    /** @var array<string, int> */
    private array $preparationLocations = [];

    private ?int $defaultLocationId = null;

    private ?int $preparationLocationId = null;

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

        $authUser = $this->seedUsers($company);
        if ($authUser instanceof User) {
            Auth::setUser($authUser);
        }

        $locationTypes = $this->ensureLocationTypes($company);
        $locations = $this->ensureLocations($company, $locationTypes);
        $defaultLocation = $locations[self::DEFAULT_LOCATION_NAME] ?? reset($locations);
        if (! $defaultLocation instanceof Location) {
            throw new RuntimeException('Impossible de déterminer la localisation par défaut pour le jeu de démonstration.');
        }

        $preparationLocation = $locations[self::PREPARATION_LOCATION_NAME] ?? $defaultLocation;

        $this->defaultLocationId = $defaultLocation->id;
        $this->preparationLocationId = $preparationLocation->id;

        $categoryIds = $this->ensureCategories($company, $locationTypes);
        $ingredients = $this->seedIngredients($company, $categoryIds, $locations, $defaultLocation);
        $preparationCategoryId = $categoryIds['Préparations Maison'] ?? (int) reset($categoryIds);
        $preparations = $this->seedPreparations(
            $company,
            $preparationCategoryId,
            $preparationLocation,
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

    private function seedUsers(Company $company): ?User
    {
        $firstUser = null;

        foreach (self::COMPANY_PROFILE['users'] as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'company_id' => $company->id,
                    'password' => 'password',
                ]
            );

            $firstUser ??= $user;
        }

        return $firstUser;
    }

    /**
     * @return array<string, LocationType>
     */
    private function ensureLocationTypes(Company $company): array
    {
        $names = array_unique(array_map(
            static fn (array $definition) => $definition['type'] ?? 'Autre',
            self::LOCATION_BLUEPRINTS
        ));

        $types = [];
        foreach ($names as $name) {
            $types[$name] = $company->locationTypes()->updateOrCreate(
                ['name' => $name],
                ['is_default' => in_array($name, ['Réfrigérateur', 'Congélateur', 'Autre'], true)]
            );
        }

        return $types;
    }

    /**
     * @param  array<string, LocationType>  $locationTypes
     * @return array<string, Location>
     */
    private function ensureLocations(Company $company, array $locationTypes): array
    {
        $locations = [];

        foreach (self::LOCATION_BLUEPRINTS as $name => $config) {
            $typeName = $config['type'] ?? 'Autre';
            $type = $locationTypes[$typeName] ?? null;

            $location = $company->locations()->where('name', $name)->first();

            if (! $location && ! empty($config['aliases'])) {
                foreach ($config['aliases'] as $alias) {
                    $aliasLocation = $company->locations()->where('name', $alias)->first();

                    if ($aliasLocation instanceof Location) {
                        $aliasLocation->update([
                            'name' => $name,
                            'location_type_id' => $type?->id,
                        ]);

                        $location = $aliasLocation->fresh();

                        break;
                    }
                }
            }

            if (! $location) {
                $location = $company->locations()->updateOrCreate(
                    ['name' => $name],
                    ['location_type_id' => $type?->id]
                );
            } else {
                $location->update(['location_type_id' => $type?->id]);
                $location = $location->fresh();
            }

            $locations[$name] = $location;
        }

        return $locations;
    }

    /**
     * @param  array<string, LocationType>  $locationTypes
     * @return array<string, int>
     */
    private function ensureCategories(Company $company, array $locationTypes): array
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

            $this->assignCategoryShelfLife($category, $locationTypes);

            $categories[$name] = $category->id;
        }

        return $categories;
    }

    /**
     * @param  array<string, LocationType>  $locationTypes
     */
    private function assignCategoryShelfLife(Category $category, array $locationTypes): void
    {
        $definition = self::CATEGORY_SHELF_LIFE[$category->name] ?? [];
        $payload = [];

        foreach ($definition as $typeName => $hours) {
            if (! isset($locationTypes[$typeName])) {
                continue;
            }

            $payload[$locationTypes[$typeName]->id] = ['shelf_life_hours' => (int) $hours];
        }

        if (empty($payload)) {
            foreach (self::DEFAULT_CATEGORY_SHELF_LIFE as $typeName => $hours) {
                if (! isset($locationTypes[$typeName])) {
                    continue;
                }

                $payload[$locationTypes[$typeName]->id] = ['shelf_life_hours' => (int) $hours];
            }
        }

        if (! empty($payload)) {
            $category->locationTypes()->sync($payload);
        }
    }

    /**
     * @return array<string, Ingredient>
     */
    private function seedIngredients(
        Company $company,
        array $categoryIds,
        array $locations,
        Location $defaultLocation
    ): array {
        $ingredients = [];
        $fallbackCategoryId = $categoryIds['Ingrédients Divers'] ?? (int) reset($categoryIds);

        foreach (self::INGREDIENTS as $name => $meta) {
            $categoryId = $categoryIds[$meta['category']] ?? $fallbackCategoryId;

            $unit = $meta['unit'] instanceof MeasurementUnit
                ? $meta['unit']
                : MeasurementUnit::from($meta['unit']);
            $baseUnit = isset($meta['base_unit'])
                ? ($meta['base_unit'] instanceof MeasurementUnit
                    ? $meta['base_unit']
                    : MeasurementUnit::from($meta['base_unit']))
                : $unit;
            $baseQuantity = isset($meta['base_quantity']) ? (float) $meta['base_quantity'] : 0.0;

            $ingredient = Ingredient::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $name,
                ],
                [
                    'category_id' => $categoryId,
                    'unit' => $unit->value,
                    'base_quantity' => $baseQuantity,
                    'base_unit' => $baseUnit->value,
                    'barcode' => $meta['barcode'] ?? null,
                ]
            );

            if (empty($ingredient->image_url) && ! empty($meta['barcode'])) {
                $imagePath = $this->storeImageFromOpenFoodFacts($name, $meta['barcode']);
                if ($imagePath) {
                    $ingredient->update(['image_url' => $imagePath]);
                }
            }

            if (! $ingredient->image_url) {
                $ingredient->update(['image_url' => $this->placeholderPath()]);
            }

            $location = $this->resolveIngredientLocation($name, $meta, $locations, $defaultLocation);

            $ingredient->locations()->sync([
                $location->id => ['quantity' => isset($meta['stock']) ? (float) $meta['stock'] : 0.0],
            ]);

            $this->ingredientLocations[$name] = $location->id;

            $ingredients[$name] = $ingredient;
        }

        return $ingredients;
    }

    /**
     * @param  array<string, Location>  $locations
     */
    private function resolveIngredientLocation(
        string $name,
        array $meta,
        array $locations,
        Location $fallback
    ): Location {
        $preferredName = self::INGREDIENT_LOCATION_OVERRIDES[$name]
            ?? self::CATEGORY_LOCATION_MAP[$meta['category']] ?? null;

        if ($preferredName && isset($locations[$preferredName])) {
            return $locations[$preferredName];
        }

        return $locations[self::DEFAULT_LOCATION_NAME] ?? $fallback;
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
            $stored = $this->images->storeFromUrl($imageUrl, self::TEMP_IMAGE_FOLDER);
        } catch (Throwable $exception) {
            $this->missingImages[] = $ingredientName.' (téléchargement)';

            return $this->productImages[$barcode] = null;
        }

        return $this->productImages[$barcode] = $stored;
    }

    private function placeholderPath(): string
    {
        if ($this->placeholderImagePath) {
            return $this->placeholderImagePath;
        }

        if ($this->images->exists(self::DEMO_PLACEHOLDER_PATH)) {
            return $this->placeholderImagePath = self::DEMO_PLACEHOLDER_PATH;
        }

        $localPlaceholder = storage_path('app/'.self::DEFAULT_PLACEHOLDER_SOURCE);
        $localAvailable = is_file($localPlaceholder) && is_readable($localPlaceholder);

        if ($localAvailable) {
            $contents = file_get_contents($localPlaceholder);

            if ($contents === false) {
                $this->command?->warn('Impossible de lire le placeholder local pour la démonstration.');
            } else {
                try {
                    if (Storage::disk('s3')->put(self::DEMO_PLACEHOLDER_PATH, $contents)) {
                        return $this->placeholderImagePath = self::DEMO_PLACEHOLDER_PATH;
                    }

                    $this->command?->warn('Impossible de stocker le placeholder de démonstration sur S3.');
                } catch (Throwable $exception) {
                    $this->command?->warn('Impossible de publier le placeholder de démonstration : '.$exception->getMessage());
                }
            }
        } else {
            $this->command?->warn('Placeholder local indisponible pour la démonstration.');
        }

        try {
            return $this->placeholderImagePath = $this->images->storePlaceholder(self::DEFAULT_PLACEHOLDER_SOURCE);
        } catch (Throwable $exception) {
            $this->command?->warn('Impossible de stocker le placeholder par défaut : '.$exception->getMessage());

            return $this->placeholderImagePath = self::DEFAULT_PLACEHOLDER_SOURCE;
        }
    }

    /**
     * @param  array<string, Ingredient>  $ingredients
     * @return array<string, Preparation>
     */
    private function seedPreparations(
        Company $company,
        int $categoryId,
        Location $preparationLocation,
        array $ingredients
    ): array {
        $cache = [];

        foreach (array_keys(self::PREPARATION_COMPONENTS) as $name) {
            $this->buildPreparation(
                $name,
                $company,
                $categoryId,
                $preparationLocation,
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
        Location $preparationLocation,
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

        $imagePath = $this->placeholderPath();

        $preparation = Preparation::updateOrCreate(
            [
                'company_id' => $company->id,
                'name' => $name,
            ],
            [
                'category_id' => $categoryId,
                'image_url' => $imagePath,
                'unit' => MeasurementUnit::UNIT->value,
                'base_quantity' => 1,
                'base_unit' => MeasurementUnit::UNIT->value,
            ]
        );

        $preparation->locations()->sync([
            $preparationLocation->id => ['quantity' => $this->preparationStock($name)],
        ]);

        $this->preparationLocations[$name] = $preparationLocation->id;

        $preparation->entities()->delete();

        foreach ($definition as $component) {
            if (isset($component['ingredient'])) {
                $ingredientName = $component['ingredient'];
                $ingredient = $ingredients[$ingredientName] ?? null;
                if (! $ingredient) {
                    $this->missingIngredients[] = $ingredientName.' (préparation '.$name.')';

                    continue;
                }

                $quantity = isset($component['quantity']) ? (float) $component['quantity'] : 0.0;
                $componentUnit = $component['unit'] ?? $ingredient->unit ?? MeasurementUnit::UNIT;
                $componentUnit = $componentUnit instanceof MeasurementUnit
                    ? $componentUnit
                    : MeasurementUnit::from($componentUnit);

                $preparation->entities()->create([
                    'entity_id' => $ingredient->id,
                    'entity_type' => Ingredient::class,
                    'location_id' => $preparationLocation->id,
                    'quantity' => $quantity,
                    'unit' => $componentUnit->value,
                ]);

                continue;
            }

            if (isset($component['preparation'])) {
                $childName = $component['preparation'];
                $child = $this->buildPreparation(
                    $childName,
                    $company,
                    $categoryId,
                    $preparationLocation,
                    $ingredients,
                    $cache
                );

                if (! $child) {
                    $this->missingComponents[] = $childName.' (préparation '.$name.')';

                    continue;
                }

                $quantity = isset($component['quantity']) ? (float) $component['quantity'] : 1.0;
                $componentUnit = $component['unit'] ?? MeasurementUnit::UNIT;
                $componentUnit = $componentUnit instanceof MeasurementUnit
                    ? $componentUnit
                    : MeasurementUnit::from($componentUnit);

                $preparation->entities()->create([
                    'entity_id' => $child->id,
                    'entity_type' => Preparation::class,
                    'location_id' => $preparationLocation->id,
                    'quantity' => $quantity,
                    'unit' => $componentUnit->value,
                ]);
            }
        }

        return $cache[$name] = $preparation;
    }

    private function preparationStock(string $name): float
    {
        return isset(self::PREPARATION_STOCK_LEVELS[$name])
            ? (float) self::PREPARATION_STOCK_LEVELS[$name]
            : 0.0;
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
     * @param  array<string, array<int, array{
     *     name: string,
     *     price: float,
     *     ingredients: array<int, array{name: string, quantity?: float, unit?: MeasurementUnit|string}>,
     *     preparations: array<int, array{name: string, quantity?: float, unit?: MeasurementUnit|string}>,
     * }>>  $dataset
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
                        'is_a_la_carte' => true,
                        'type' => $menuType,
                        'price' => $entry['price'],
                    ]
                );

                if (! $menu->image_url) {
                    $menu->update(['image_url' => $this->placeholderPath()]);
                }

                if ($menuCategory instanceof MenuCategory) {
                    $menu->categories()->syncWithoutDetaching([$menuCategory->id]);
                }

                foreach ($entry['ingredients'] as $component) {
                    $component = is_string($component) ? ['name' => $component] : $component;
                    $ingredientName = $component['name'] ?? null;

                    if (! $ingredientName) {
                        continue;
                    }

                    $ingredient = $ingredients[$ingredientName] ?? null;
                    if (! $ingredient) {
                        $this->missingComponents[] = $ingredientName.' (menu '.$entry['name'].')';

                        continue;
                    }

                    $quantity = array_key_exists('quantity', $component)
                        ? (float) $component['quantity']
                        : 1.0;

                    $componentUnit = $component['unit'] ?? $ingredient->unit ?? MeasurementUnit::UNIT;
                    $componentUnit = $componentUnit instanceof MeasurementUnit
                        ? $componentUnit
                        : MeasurementUnit::from($componentUnit);

                    $locationId = $this->ingredientLocations[$ingredientName]
                        ?? $this->defaultLocationId
                        ?? $defaultLocation->id;

                    MenuItem::updateOrCreate(
                        [
                            'menu_id' => $menu->id,
                            'entity_id' => $ingredient->id,
                            'entity_type' => Ingredient::class,
                        ],
                        [
                            'location_id' => $locationId,
                            'quantity' => $quantity,
                            'unit' => $componentUnit->value,
                        ]
                    );
                }

                foreach ($entry['preparations'] as $component) {
                    $component = is_string($component) ? ['name' => $component] : $component;
                    $preparationName = $component['name'] ?? null;

                    if (! $preparationName) {
                        continue;
                    }

                    $preparation = $preparations[$preparationName] ?? null;
                    if (! $preparation) {
                        $this->missingComponents[] = $preparationName.' (menu '.$entry['name'].')';

                        continue;
                    }

                    $quantity = array_key_exists('quantity', $component)
                        ? (float) $component['quantity']
                        : 1.0;

                    $componentUnit = $component['unit'] ?? MeasurementUnit::UNIT;
                    $componentUnit = $componentUnit instanceof MeasurementUnit
                        ? $componentUnit
                        : MeasurementUnit::from($componentUnit);

                    $locationId = $this->preparationLocations[$preparationName]
                        ?? $this->preparationLocationId
                        ?? $this->defaultLocationId
                        ?? $defaultLocation->id;

                    MenuItem::updateOrCreate(
                        [
                            'menu_id' => $menu->id,
                            'entity_id' => $preparation->id,
                            'entity_type' => Preparation::class,
                        ],
                        [
                            'location_id' => $locationId,
                            'quantity' => $quantity,
                            'unit' => $componentUnit->value,
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
