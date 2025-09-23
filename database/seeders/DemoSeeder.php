<?php

namespace Database\Seeders;

use App\Enums\Allergen;
use App\Enums\MeasurementUnit;
use App\Enums\MenuServiceType;
use App\Enums\OrderStatus;
use App\Enums\OrderStepStatus;
use App\Enums\StepMenuStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuType;
use App\Models\MenuTypePublicOrder;
use App\Models\Order;
use App\Models\OrderStep;
use App\Models\Preparation;
use App\Models\Room;
use App\Models\StepMenu;
use App\Models\StockMovement;
use App\Models\Table;
use App\Models\User;
use App\Services\ImageService;
use App\Services\OpenFoodFactsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;
use Throwable;

class DemoSeeder extends Seeder
{
    private const COMPANY_PROFILE = [
        'name' => 'Maison Gustave',
        'open_food_facts_language' => 'fr',
        'contact' => [
            'contact_name' => 'Gustave Renoir',
            'contact_email' => 'contact@maison-gustave.fr',
            'contact_phone' => '+33 1 42 68 10 10',
            'address_line' => '8 Rue de la Gastronomie',
            'postal_code' => '75002',
            'city' => 'Paris',
            'country' => 'France',
        ],
        'business_hours' => [
            'monday' => [
                ['opens_at' => '11:30', 'closes_at' => '14:30'],
                ['opens_at' => '18:30', 'closes_at' => '22:30'],
            ],
            'tuesday' => [
                ['opens_at' => '11:30', 'closes_at' => '14:30'],
                ['opens_at' => '18:30', 'closes_at' => '22:30'],
            ],
            'wednesday' => [
                ['opens_at' => '11:30', 'closes_at' => '14:30'],
                ['opens_at' => '18:30', 'closes_at' => '22:30'],
            ],
            'thursday' => [
                ['opens_at' => '11:30', 'closes_at' => '14:30'],
                ['opens_at' => '18:30', 'closes_at' => '22:30'],
            ],
            'friday' => [
                ['opens_at' => '11:30', 'closes_at' => '14:30'],
                ['opens_at' => '18:30', 'closes_at' => '23:30'],
            ],
            'saturday' => [
                ['opens_at' => '11:30', 'closes_at' => '15:00'],
                ['opens_at' => '18:30', 'closes_at' => '02:00', 'is_overnight' => true],
            ],
            'sunday' => [
                ['opens_at' => '11:30', 'closes_at' => '15:00'],
            ],
        ],
        'users' => [
            ['name' => 'Adrien', 'email' => 'adrien@example.com'],
            ['name' => 'Thomas', 'email' => 'thomas@example.com'],
            ['name' => 'Luca', 'email' => 'luca@example.com'],
            ['name' => 'Brandon', 'email' => 'brandon@example.com'],
            ['name' => 'Antoine', 'email' => 'antoine@example.com'],
        ],
    ];

    private function seedAdditionalCompany(): void
    {
        $company = Company::updateOrCreate(
            ['name' => 'Bistro Maelle'],
            ['open_food_facts_language' => 'fr']
        );

        User::updateOrCreate(
            ['email' => 'maelle@example.com'],
            [
                'name' => 'Maelle',
                'company_id' => $company->id,
                'password' => 'password',
            ]
        );
    }

    private const MENU_BLUEPRINT_JSON = <<<'JSON'
    {
        "hors_doeuvres": [
            {
                "nom": "Notre pâté en croûte, pickles de légumes",
                "prix": 28,
                "description": "Terrine artisanale aux viandes sélectionnées servie avec des pickles croquants préparés le matin même.",
                "service_type": "PREP",
                "is_returnable": false,
                "preparations": {
                    "Pâté en croûte": [
                        "Pâte brisée (farine, beurre, eau, sel)",
                        "Farce de porc maison (échine de porc, veau, foie de volaille, œufs, crème, sel, poivre, armagnac, épices)",
                        "Gelée (fond de volaille, gélatine)"
                    ],
                    "Pickles de légumes": [
                        "Carottes",
                        "Chou-fleur",
                        "Oignons",
                        "Cornichons",
                        "Marinade (vinaigre blanc, eau, sucre, sel, graines de moutarde, poivre en grain)"
                    ]
                }
            },
            {
                "nom": "Foie gras de canard, brioche parisienne",
                "prix": 32,
                "description": "Foie gras mi-cuit assaisonné maison accompagné d'une brioche parisienne dorée au beurre.",
                "service_type": "PREP",
                "is_returnable": false,
                "ingredients": [
                    "Foie gras de canard cru"
                ],
                "preparations": {
                    "Brioche parisienne": [
                        "Farine",
                        "Œufs",
                        "Beurre",
                        "Lait",
                        "Sucre",
                        "Levure de boulanger",
                        "Sel"
                    ]
                }
            },
            {
                "nom": "Homard bleu rafraîchi, haricots verts et amandes fraîches",
                "prix": 38,
                "description": "Chair de homard bleu juste pochée, assaisonnée à l'huile d'olive et servie avec des légumes croquants.",
                "service_type": "PREP",
                "is_returnable": false,
                "ingredients": [
                    "Homard bleu",
                    "Haricots verts frais",
                    "Amandes fraîches",
                    "Huile d’olive",
                    "Citron",
                    "Sel",
                    "Poivre"
                ]
            },
            {
                "nom": "Tomate de plein champs fondante, anchois et basilic",
                "prix": 30,
                "description": "Tomate mûrie au soleil confite doucement, relevée d'anchois de Méditerranée et de basilic frais.",
                "service_type": "DIRECT",
                "is_returnable": false,
                "ingredients": [
                    "Tomate de plein champ",
                    "Filets d’anchois",
                    "Basilic frais",
                    "Huile d’olive"
                ]
            }
        ],
        "plats": [
            {
                "nom": "Dos de bar doré, courgette trompette et jus d’une marinière",
                "prix": 38,
                "description": "Bar de ligne snacké côté peau, servi avec des courgettes trompettes glacées et un jus de marinière réduit.",
                "service_type": "PREP",
                "is_returnable": false,
                "ingredients": [
                    "Dos de bar",
                    "Courgette trompette"
                ],
                "preparations": {
                    "Jus de marinière": [
                        "Vin blanc sec",
                        "Échalotes",
                        "Beurre",
                        "Persil",
                        "Sel",
                        "Poivre"
                    ]
                }
            },
            {
                "nom": "Sole à la meunière, cassolette d’artichauts (pour deux)",
                "prix": 160,
                "description": "Sole entière meunière à partager, accompagnée d'une cassolette d'artichauts tournés au jus.",
                "service_type": "PREP",
                "is_returnable": true,
                "preparations": {
                    "Sole à la meunière": [
                        "Sole",
                        "Beurre",
                        "Farine",
                        "Jus de citron",
                        "Persil"
                    ],
                    "Cassolette d’artichauts": [
                        "Artichauts frais",
                        "Fond de volaille",
                        "Huile d’olive",
                        "Ail",
                        "Sel",
                        "Poivre"
                    ]
                }
            }
        ],
        "fromage": [
            {
                "nom": "Fromages de France",
                "prix": 16,
                "description": "Plateau de fromages affinés sélectionnés auprès de producteurs français artisanaux.",
                "service_type": "DIRECT",
                "is_returnable": true,
                "ingredients": [
                    "Sélection de fromages de vache, chèvre, brebis"
                ]
            }
        ],
        "desserts": [
            {
                "nom": "Millefeuille classique à la vanille",
                "prix": 14,
                "description": "Feuilles croustillantes et crème pâtissière infusée à la vanille Bourbon montées à la minute.",
                "service_type": "PREP",
                "is_returnable": false,
                "preparations": {
                    "Millefeuille": [
                        "Pâte feuilletée (farine, beurre, eau, sel)",
                        "Crème pâtissière à la vanille (lait, sucre, jaunes d’œuf, fécule, gousse de vanille)",
                        "Sucre glace"
                    ]
                }
            },
            {
                "nom": "Pêche Melba",
                "prix": 14,
                "description": "Pêches pochées, glace vanille et coulis de framboise pour un dessert frais et léger.",
                "service_type": "PREP",
                "is_returnable": false,
                "preparations": {
                    "Pêches pochées": [
                        "Pêches",
                        "Sirop",
                        "Vanille"
                    ],
                    "Coulis de framboise": [
                        "Framboises",
                        "Sucre"
                    ]
                },
                "ingredients": [
                    "Glace vanille"
                ]
            }
        ]
    }
    JSON;

    private const MENU_CATEGORY_LABELS = [
        'hors_doeuvres' => 'Entrées de la Maison',
        'plats' => 'Plats Signature',
        'fromage' => 'Sélection Fromagère',
        'desserts' => 'Douceurs sucrées',
    ];

    private const MENU_TYPE_MAP = [
        'hors_doeuvres' => 'Entrées',
        'plats' => 'Plats',
        'fromage' => 'Accompagnements',
        'desserts' => 'Desserts',
    ];

    private const MENU_TYPE_POSITIONS = [
        'Entrées' => 0,
        'Plats' => 1,
        'Desserts' => 2,
        'Accompagnements' => 3,
    ];

    private const ROOM_BLUEPRINTS = [
        [
            'code' => 'MG-HALL',
            'name' => 'Grand Hall Maison Gustave',
        ],
        [
            'code' => 'MG-TERR',
            'name' => 'Terrasse Jardin Maison Gustave',
        ],
    ];

    private const TABLE_BLUEPRINTS = [
        [
            'label' => 'MG-HALL-01',
            'seats' => 4,
            'room_code' => 'MG-HALL',
        ],
        [
            'label' => 'MG-HALL-02',
            'seats' => 2,
            'room_code' => 'MG-HALL',
        ],
        [
            'label' => 'MG-TERR-01',
            'seats' => 4,
            'room_code' => 'MG-TERR',
        ],
        [
            'label' => 'MG-TERR-02',
            'seats' => 6,
            'room_code' => 'MG-TERR',
        ],
    ];

    private const MENU_IMAGE_DIRECTORIES = [
        'private/seeders/images',
        'seeders/images',
        'images/seeders/images',
    ];

    private const MENU_IMAGE_EXTENSIONS = ['jpeg', 'jpg', 'png', 'webp', 'gif', 'avif'];

    private const ORDER_BLUEPRINTS = [
        [
            'table' => 'MG-HALL-01',
            'user' => 'adrien@example.com',
            'status' => OrderStatus::PENDING,
            'timeline' => [
                'minutes_ago' => 45,
            ],
            'steps' => [
                [
                    'status' => OrderStepStatus::IN_PREP,
                    'menus' => [
                        [
                            'name' => 'Notre pâté en croûte, pickles de légumes',
                            'quantity' => 2,
                            'status' => StepMenuStatus::IN_PREP,
                        ],
                        [
                            'name' => 'Foie gras de canard, brioche parisienne',
                            'quantity' => 1,
                            'status' => StepMenuStatus::IN_PREP,
                            'note' => 'Servir avec brioche tiède',
                        ],
                    ],
                ],
            ],
        ],
        [
            'table' => 'MG-HALL-02',
            'user' => 'thomas@example.com',
            'status' => OrderStatus::SERVED,
            'timeline' => [
                'minutes_ago' => 110,
                'served_after' => 55,
            ],
            'steps' => [
                [
                    'status' => OrderStepStatus::READY,
                    'served_after' => 35,
                    'menus' => [
                        [
                            'name' => 'Homard bleu rafraîchi, haricots verts et amandes fraîches',
                            'quantity' => 1,
                            'status' => StepMenuStatus::READY,
                        ],
                    ],
                ],
                [
                    'status' => OrderStepStatus::SERVED,
                    'served_after' => 55,
                    'menus' => [
                        [
                            'name' => 'Dos de bar doré, courgette trompette et jus d’une marinière',
                            'quantity' => 1,
                            'status' => StepMenuStatus::SERVED,
                        ],
                    ],
                ],
            ],
        ],
        [
            'table' => 'MG-TERR-01',
            'user' => 'antoine@example.com',
            'status' => OrderStatus::PAYED,
            'timeline' => [
                'minutes_ago' => 180,
                'served_after' => 70,
                'payed_after' => 95,
            ],
            'steps' => [
                [
                    'status' => OrderStepStatus::SERVED,
                    'served_after' => 60,
                    'menus' => [
                        [
                            'name' => 'Sole à la meunière, cassolette d’artichauts (pour deux)',
                            'quantity' => 1,
                            'status' => StepMenuStatus::SERVED,
                        ],
                        [
                            'name' => 'Fromages de France',
                            'quantity' => 1,
                            'status' => StepMenuStatus::SERVED,
                        ],
                    ],
                ],
                [
                    'status' => OrderStepStatus::SERVED,
                    'served_after' => 70,
                    'menus' => [
                        [
                            'name' => 'Pêche Melba',
                            'quantity' => 2,
                            'status' => StepMenuStatus::SERVED,
                        ],
                    ],
                ],
            ],
        ],
        [
            'table' => 'MG-TERR-02',
            'user' => 'brandon@example.com',
            'status' => OrderStatus::CANCELED,
            'timeline' => [
                'minutes_ago' => 60,
                'canceled_after' => 20,
            ],
            'steps' => [
                [
                    'status' => OrderStepStatus::IN_PREP,
                    'menus' => [
                        [
                            'name' => 'Millefeuille classique à la vanille',
                            'quantity' => 3,
                            'status' => StepMenuStatus::IN_PREP,
                        ],
                    ],
                ],
            ],
        ],
        [
            'table' => 'MG-HALL-01',
            'user' => 'luca@example.com',
            'status' => OrderStatus::PENDING,
            'timeline' => [
                'minutes_ago' => 25,
            ],
            'steps' => [
                [
                    'status' => OrderStepStatus::IN_PREP,
                    'menus' => [
                        [
                            'name' => 'Tomate de plein champs fondante, anchois et basilic',
                            'quantity' => 1,
                            'status' => StepMenuStatus::IN_PREP,
                        ],
                        [
                            'name' => 'Foie gras de canard, brioche parisienne',
                            'quantity' => 1,
                            'status' => StepMenuStatus::IN_PREP,
                        ],
                    ],
                ],
            ],
        ],
        [
            'table' => 'MG-HALL-02',
            'user' => 'adrien@example.com',
            'status' => OrderStatus::PENDING,
            'timeline' => [
                'minutes_ago' => 15,
            ],
            'steps' => [
                [
                    'status' => OrderStepStatus::READY,
                    'menus' => [
                        [
                            'name' => 'Millefeuille classique à la vanille',
                            'quantity' => 2,
                            'status' => StepMenuStatus::READY,
                        ],
                    ],
                ],
            ],
        ],
        [
            'table' => 'MG-TERR-02',
            'user' => 'thomas@example.com',
            'status' => OrderStatus::SERVED,
            'timeline' => [
                'minutes_ago' => 90,
                'served_after' => 40,
            ],
            'steps' => [
                [
                    'status' => OrderStepStatus::READY,
                    'served_after' => 35,
                    'menus' => [
                        [
                            'name' => 'Dos de bar doré, courgette trompette et jus d’une marinière',
                            'quantity' => 1,
                            'status' => StepMenuStatus::READY,
                        ],
                    ],
                ],
                [
                    'status' => OrderStepStatus::SERVED,
                    'served_after' => 40,
                    'menus' => [
                        [
                            'name' => 'Homard bleu rafraîchi, haricots verts et amandes fraîches',
                            'quantity' => 1,
                            'status' => StepMenuStatus::SERVED,
                        ],
                    ],
                ],
            ],
        ],
        [
            'table' => 'MG-HALL-01',
            'user' => 'brandon@example.com',
            'status' => OrderStatus::SERVED,
            'timeline' => [
                'minutes_ago' => 70,
                'served_after' => 45,
            ],
            'steps' => [
                [
                    'status' => OrderStepStatus::SERVED,
                    'served_after' => 45,
                    'menus' => [
                        [
                            'name' => 'Notre pâté en croûte, pickles de légumes',
                            'quantity' => 2,
                            'status' => StepMenuStatus::SERVED,
                        ],
                    ],
                ],
            ],
        ],
        [
            'table' => 'MG-TERR-01',
            'user' => 'adrien@example.com',
            'status' => OrderStatus::PAYED,
            'timeline' => [
                'minutes_ago' => 200,
                'served_after' => 80,
                'payed_after' => 30,
            ],
            'steps' => [
                [
                    'status' => OrderStepStatus::SERVED,
                    'served_after' => 75,
                    'menus' => [
                        [
                            'name' => 'Sole à la meunière, cassolette d’artichauts (pour deux)',
                            'quantity' => 1,
                            'status' => StepMenuStatus::SERVED,
                        ],
                        [
                            'name' => 'Fromages de France',
                            'quantity' => 1,
                            'status' => StepMenuStatus::SERVED,
                        ],
                    ],
                ],
            ],
        ],
        [
            'table' => 'MG-HALL-02',
            'user' => 'luca@example.com',
            'status' => OrderStatus::PAYED,
            'timeline' => [
                'minutes_ago' => 160,
                'served_after' => 60,
                'payed_after' => 35,
            ],
            'steps' => [
                [
                    'status' => OrderStepStatus::SERVED,
                    'served_after' => 55,
                    'menus' => [
                        [
                            'name' => 'Foie gras de canard, brioche parisienne',
                            'quantity' => 1,
                            'status' => StepMenuStatus::SERVED,
                        ],
                        [
                            'name' => 'Tomate de plein champs fondante, anchois et basilic',
                            'quantity' => 1,
                            'status' => StepMenuStatus::SERVED,
                        ],
                    ],
                ],
            ],
        ],
        [
            'table' => 'MG-TERR-02',
            'user' => 'antoine@example.com',
            'status' => OrderStatus::CANCELED,
            'timeline' => [
                'minutes_ago' => 50,
                'canceled_after' => 25,
            ],
            'steps' => [
                [
                    'status' => OrderStepStatus::IN_PREP,
                    'menus' => [
                        [
                            'name' => 'Pêche Melba',
                            'quantity' => 3,
                            'status' => StepMenuStatus::IN_PREP,
                        ],
                    ],
                ],
            ],
        ],
        [
            'table' => 'MG-HALL-01',
            'user' => 'thomas@example.com',
            'status' => OrderStatus::CANCELED,
            'timeline' => [
                'minutes_ago' => 30,
                'canceled_after' => 10,
            ],
            'steps' => [
                [
                    'status' => OrderStepStatus::IN_PREP,
                    'menus' => [
                        [
                            'name' => 'Dos de bar doré, courgette trompette et jus d’une marinière',
                            'quantity' => 1,
                            'status' => StepMenuStatus::IN_PREP,
                        ],
                    ],
                ],
            ],
        ],
        [
            'table' => 'MG-TERR-01',
            'user' => 'brandon@example.com',
            'status' => OrderStatus::PENDING,
            'timeline' => [
                'minutes_ago' => 18,
            ],
            'steps' => [
                [
                    'status' => OrderStepStatus::IN_PREP,
                    'menus' => [
                        [
                            'name' => 'Foie gras de canard, brioche parisienne',
                            'quantity' => 1,
                            'status' => StepMenuStatus::IN_PREP,
                            'note' => 'Allergie arachides à vérifier',
                        ],
                    ],
                ],
            ],
        ],
        [
            'table' => 'MG-TERR-02',
            'user' => 'adrien@example.com',
            'status' => OrderStatus::SERVED,
            'timeline' => [
                'minutes_ago' => 140,
                'served_after' => 65,
            ],
            'steps' => [
                [
                    'status' => OrderStepStatus::SERVED,
                    'served_after' => 65,
                    'menus' => [
                        [
                            'name' => 'Fromages de France',
                            'quantity' => 1,
                            'status' => StepMenuStatus::SERVED,
                        ],
                        [
                            'name' => 'Millefeuille classique à la vanille',
                            'quantity' => 2,
                            'status' => StepMenuStatus::SERVED,
                        ],
                    ],
                ],
            ],
        ],
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
            'stock' => 6000,
            'barcode' => '4056489565536',
            'allergens' => [Allergen::GLUTEN->value],
        ],
        'Beurre' => [
            'category' => 'Produits Laitiers',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 250,
            'stock' => 3000,
            'barcode' => '26064413',
            'allergens' => [Allergen::MILK->value],
        ],
        'Eau' => [
            'category' => 'Boissons',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 1000,
            'stock' => 120,
            'barcode' => '1234500001857',
        ],
        'Sel' => [
            'category' => 'Épicerie',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 1000,
            'stock' => 1500,
            'barcode' => '10020811',
        ],
        'Échine de porc' => [
            'category' => 'Viandes',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 180,
            'stock' => 12,
            'barcode' => '0207024022173',
        ],
        'Veau' => [
            'category' => 'Viandes',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 160,
            'stock' => 10,
            'barcode' => '2695314012009',
        ],
        'Foie de volaille' => [
            'category' => 'Viandes',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 90,
            'stock' => 18,
            'barcode' => '0215085018561',
        ],
        'Œufs' => [
            'category' => 'Œufs',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::UNIT,
            'base_quantity' => 1,
            'stock' => 180,
            'barcode' => '3560070432080',
            'allergens' => [Allergen::EGGS->value],
        ],
        'Crème' => [
            'category' => 'Produits Laitiers',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 1000,
            'stock' => 24,
            'barcode' => '3258561419299',
            'allergens' => [Allergen::MILK->value],
        ],
        'Poivre' => [
            'category' => 'Épices',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 250,
            'stock' => 300,
            'barcode' => '8720254531779',
        ],
        'Armagnac' => [
            'category' => 'Spiritueux',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 700,
            'stock' => 4.2,
            'barcode' => '3560070575480',
        ],
        'Épices' => [
            'category' => 'Épices',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 200,
            'stock' => 220,
            'barcode' => '3700483800544',
        ],
        'Fond de volaille' => [
            'category' => 'Épicerie',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 1000,
            'stock' => 12,
            'barcode' => '3256225451647',
        ],
        'Gélatine' => [
            'category' => 'Épicerie',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 200,
            'stock' => 500,
            'barcode' => '3256225731978',
        ],
        'Carottes' => [
            'category' => 'Légumes',
            'unit' => MeasurementUnit::KILOGRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 1000,
            'stock' => 18,
            'barcode' => '3596710431151',
        ],
        'Chou-fleur' => [
            'category' => 'Légumes',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 900,
            'stock' => 12,
            'barcode' => '3560070122349',
        ],
        'Oignons' => [
            'category' => 'Légumes',
            'unit' => MeasurementUnit::KILOGRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 1000,
            'stock' => 20,
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
            'stock' => 10,
            'barcode' => '3077311522405',
            'allergens' => [Allergen::SULPHITES->value],
        ],
        'Sucre' => [
            'category' => 'Épicerie',
            'unit' => MeasurementUnit::KILOGRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 1000,
            'stock' => 7000,
            'barcode' => '3596710473557',
        ],
        'Graines de moutarde' => [
            'category' => 'Épices',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 200,
            'stock' => 180,
            'barcode' => '7610845400434',
            'allergens' => [Allergen::MUSTARD->value],
        ],
        'Foie gras de canard cru' => [
            'category' => 'Viandes',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 500,
            'stock' => 6,
            'barcode' => '26078410',
        ],
        'Lait' => [
            'category' => 'Produits Laitiers',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 1000,
            'stock' => 40,
            'barcode' => '3428272970017',
            'allergens' => [Allergen::MILK->value],
        ],
        'Levure de boulanger' => [
            'category' => 'Épicerie',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 100,
            'stock' => 320,
            'barcode' => '2006050036622',
        ],
        'Homard bleu' => [
            'category' => 'Fruits de Mer',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 600,
            'stock' => 0,
            'barcode' => '3770000648317',
            'allergens' => [Allergen::CRUSTACEANS->value],
        ],
        'Haricots verts frais' => [
            'category' => 'Légumes',
            'unit' => MeasurementUnit::KILOGRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 1000,
            'stock' => 10,
            'barcode' => '3760086270076',
        ],
        'Amandes fraîches' => [
            'category' => 'Fruits secs',
            'unit' => MeasurementUnit::KILOGRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 500,
            'stock' => 5,
            'barcode' => '3700194630287',
            'allergens' => [Allergen::TREE_NUTS->value],
        ],
        'Huile d’olive' => [
            'category' => 'Épicerie',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 1000,
            'stock' => 18,
            'barcode' => '3424096003078',
        ],
        'Citron' => [
            'category' => 'Fruits',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 120,
            'stock' => 48,
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
            'stock' => 400,
            'barcode' => '3218370591821',
            'allergens' => [Allergen::FISH->value],
        ],
        'Basilic frais' => [
            'category' => 'Herbes aromatiques',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 200,
            'stock' => 150,
            'barcode' => '3411061111029',
        ],
        'Dos de bar' => [
            'category' => 'Poissons',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 280,
            'stock' => 8,
            'barcode' => '3664335055264',
            'allergens' => [Allergen::FISH->value],
        ],
        'Courgette trompette' => [
            'category' => 'Légumes',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 200,
            'stock' => 30,
            'barcode' => '2306375001603',
        ],
        'Vin blanc sec' => [
            'category' => 'Boissons',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 750,
            'stock' => 18,
            'barcode' => '3660989151932',
            'allergens' => [Allergen::SULPHITES->value],
        ],
        'Échalotes' => [
            'category' => 'Légumes',
            'unit' => MeasurementUnit::KILOGRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 1000,
            'stock' => 14,
            'barcode' => '8431876150353',
        ],
        'Persil' => [
            'category' => 'Herbes aromatiques',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 200,
            'stock' => 220,
            'barcode' => '2006050101283',
        ],
        'Sole' => [
            'category' => 'Poissons',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 350,
            'stock' => 6,
            'barcode' => '0059749982474',
            'allergens' => [Allergen::FISH->value],
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
            'stock' => 20,
            'barcode' => '3256220652766',
        ],
        'Ail' => [
            'category' => 'Légumes',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 60,
            'stock' => 30,
            'barcode' => '3256228100191',
        ],
        'Sucre glace' => [
            'category' => 'Épicerie',
            'unit' => MeasurementUnit::KILOGRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 1000,
            'stock' => 3500,
            'barcode' => '3220035730001',
        ],
        'Gousse de vanille' => [
            'category' => 'Épices',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 6,
            'stock' => 60,
            'barcode' => '3256225732043',
        ],
        'Jaunes d’œuf' => [
            'category' => 'Œufs',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::UNIT,
            'base_quantity' => 1,
            'stock' => 140,
            'barcode' => '3439496001838',
            'allergens' => [Allergen::EGGS->value],
        ],
        'Fécule' => [
            'category' => 'Farines',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 500,
            'stock' => 700,
            'barcode' => '3347431805482',
        ],
        'Pêches' => [
            'category' => 'Fruits',
            'unit' => MeasurementUnit::UNIT,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 180,
            'stock' => 30,
            'barcode' => '3276559409466',
        ],
        'Sirop' => [
            'category' => 'Épicerie',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 1000,
            'stock' => 16,
            'barcode' => '5708776000877',
        ],
        'Vanille' => [
            'category' => 'Épices',
            'unit' => MeasurementUnit::GRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 100,
            'stock' => 220,
            'barcode' => '6133798001790',
        ],
        'Framboises' => [
            'category' => 'Fruits',
            'unit' => MeasurementUnit::KILOGRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 1000,
            'stock' => 9,
            'barcode' => '3385630118309',
        ],
        'Glace vanille' => [
            'category' => 'Desserts',
            'unit' => MeasurementUnit::LITRE,
            'base_unit' => MeasurementUnit::MILLILITRE,
            'base_quantity' => 1000,
            'stock' => 20,
            'barcode' => '26048154',
            'allergens' => [Allergen::MILK->value, Allergen::EGGS->value],
        ],
        'Sélection de fromages de vache, chèvre, brebis' => [
            'category' => 'Fromages',
            'unit' => MeasurementUnit::KILOGRAM,
            'base_unit' => MeasurementUnit::GRAM,
            'base_quantity' => 1500,
            'stock' => 14,
            'barcode' => '0200340018370',
            'allergens' => [Allergen::MILK->value],
        ],
    ];

    private const TEMP_IMAGE_FOLDER = 'tmp/demo-seeder/ingredients';

    private const DEFAULT_PLACEHOLDER_SOURCE = 'private/images/placeholder.svg';

    private const DEFAULT_PLACEHOLDER_DESTINATION = 'tmp/demo-seeder/placeholder.svg';

    private const MENU_PLACEHOLDER_DESTINATION = 'tmp/demo-seeder/menu-placeholder.png';

    private const MENU_PLACEHOLDER_SOURCES = [
        'private/images/playsolder.png',
        'private/images/placeholder.png',
        'public/playsolder.png',
        'public/placeholder.png',
    ];

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
        'Pâté en croûte' => 4,
        'Pickles de légumes' => 6,
        'Brioche parisienne' => 8,
        'Jus de marinière' => 6,
        'Sole à la meunière' => 0,
        'Cassolette d’artichauts' => 5,
        'Millefeuille' => 10,
        'Pêches pochées' => 6,
        'Coulis de framboise' => 6,
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

    private ?string $menuPlaceholderImagePath = null;

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

        $contact = self::COMPANY_PROFILE['contact'] ?? [];
        if (is_array($contact) && $contact !== []) {
            $company->fill($contact);
        }

        if (! $company->logo_path) {
            $company->logo_path = $this->placeholderPath();
        }

        $company->save();

        $businessHours = self::COMPANY_PROFILE['business_hours'] ?? [];
        if (is_array($businessHours) && $businessHours !== []) {
            $this->seedCompanyBusinessHours($company, $businessHours);
        }

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
        $this->seedIngredientStockMovements($ingredients);
        $preparationCategoryId = $categoryIds['Préparations Maison'] ?? (int) reset($categoryIds);
        $preparations = $this->seedPreparations(
            $company,
            $preparationCategoryId,
            $preparationLocation,
            $ingredients
        );
        $menuCategories = $this->ensureMenuCategories($company);
        $menuSections = $this->menuSections();

        $this->seedMenus(
            $company,
            $menuSections,
            $menuCategories,
            $ingredients,
            $preparations,
            $defaultLocation
        );

        $this->seedOrders($company);

        $this->seedAdditionalCompany();

        $this->report();
    }

    /**
     * @param  array<int|string, array<int, array<string, mixed>>>  $schedule
     */
    private function seedCompanyBusinessHours(Company $company, array $schedule): void
    {
        $company->businessHours()->delete();

        $records = [];

        foreach ($schedule as $day => $entries) {
            $dayOfWeek = $this->normalizeDayOfWeek($day);

            if ($dayOfWeek === null || ! is_array($entries) || $entries === []) {
                continue;
            }

            $sequence = 1;

            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $opensAt = $this->normalizeTimeString($entry['opens_at'] ?? null);
                $closesAt = $this->normalizeTimeString($entry['closes_at'] ?? null);

                if (! $opensAt || ! $closesAt) {
                    continue;
                }

                $isOvernight = $this->determineOvernight(
                    $opensAt,
                    $closesAt,
                    (bool) ($entry['is_overnight'] ?? false)
                );

                $records[] = [
                    'day_of_week' => $dayOfWeek,
                    'opens_at' => $opensAt,
                    'closes_at' => $closesAt,
                    'is_overnight' => $isOvernight,
                    'sequence' => $sequence++,
                ];
            }
        }

        if ($records === []) {
            return;
        }

        $company->businessHours()->createMany($records);
    }

    private function normalizeDayOfWeek(mixed $value): ?int
    {
        if (is_int($value) || ctype_digit((string) $value)) {
            $intValue = (int) $value;

            return $intValue >= 1 && $intValue <= 7 ? $intValue : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = str_replace(['-', ' '], ['_', '_'], mb_strtolower(trim($value)));

        $map = [
            'monday' => 1,
            'mon' => 1,
            'lundi' => 1,
            'tuesday' => 2,
            'tue' => 2,
            'tues' => 2,
            'mardi' => 2,
            'wednesday' => 3,
            'wed' => 3,
            'mercredi' => 3,
            'thursday' => 4,
            'thu' => 4,
            'thur' => 4,
            'thurs' => 4,
            'jeudi' => 4,
            'friday' => 5,
            'fri' => 5,
            'vendredi' => 5,
            'saturday' => 6,
            'sat' => 6,
            'samedi' => 6,
            'sunday' => 7,
            'sun' => 7,
            'dimanche' => 7,
        ];

        return $map[$normalized] ?? null;
    }

    private function normalizeTimeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $string, $matches)) {
            $hour = max(0, min(23, (int) $matches[1]));
            $minute = max(0, min(59, (int) $matches[2]));
            $second = isset($matches[3]) ? max(0, min(59, (int) $matches[3])) : 0;

            return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
        }

        return null;
    }

    private function determineOvernight(string $opensAt, string $closesAt, bool $flag): bool
    {
        $openMinutes = $this->timeToMinutes($opensAt);
        $closeMinutes = $this->timeToMinutes($closesAt);

        return $flag || $closeMinutes <= $openMinutes;
    }

    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', substr($time, 0, 5));
        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);

        return ($hour * 60) + $minute;
    }

    /**
     * @return array<string, array<int, array{
     *     name: string,
     *     price: float,
     *     description: ?string,
     *     service_type: string,
     *     is_returnable: bool,
     *     ingredients: array<int, array{name: string, quantity: float, unit: MeasurementUnit}>,
     *     preparations: array<int, array{name: string, quantity: float, unit: MeasurementUnit}>,
     * }>>
     */
    private function menuSections(): array
    {
        try {
            $decoded = json_decode(self::MENU_BLUEPRINT_JSON, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Impossible de décoder le jeu de données du menu de démonstration : '.$exception->getMessage(), 0, $exception);
        }

        if (! is_array($decoded)) {
            return [];
        }

        $sections = [];

        foreach ($decoded as $sectionKey => $entries) {
            if (! is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $name = $entry['nom'] ?? null;

                if (! is_string($name) || trim($name) === '') {
                    continue;
                }

                $price = isset($entry['prix']) ? (float) $entry['prix'] : 0.0;

                $description = null;
                if (array_key_exists('description', $entry) && is_string($entry['description'])) {
                    $description = trim($entry['description']);
                    $description = $description === '' ? null : $description;
                }

                $serviceType = $entry['service_type'] ?? MenuServiceType::PREP->value;
                if ($serviceType instanceof MenuServiceType) {
                    $serviceType = $serviceType->value;
                } elseif (is_string($serviceType)) {
                    $candidate = strtoupper($serviceType);
                    $serviceType = in_array($candidate, MenuServiceType::values(), true)
                        ? $candidate
                        : MenuServiceType::PREP->value;
                } else {
                    $serviceType = MenuServiceType::PREP->value;
                }

                $isReturnable = isset($entry['is_returnable']) ? (bool) $entry['is_returnable'] : false;

                $ingredients = $this->normalizeMenuComponents($entry['ingredients'] ?? [], false);
                $preparations = $this->normalizeMenuComponents(
                    $this->extractPreparationNames($entry['preparations'] ?? []),
                    true
                );

                $sections[$sectionKey][] = [
                    'name' => $name,
                    'price' => $price,
                    'description' => $description,
                    'service_type' => $serviceType,
                    'is_returnable' => $isReturnable,
                    'ingredients' => $ingredients,
                    'preparations' => $preparations,
                ];
            }
        }

        return $sections;
    }

    /**
     * @param  array<int|string, mixed>  $preparations
     * @return array<int, string>
     */
    private function extractPreparationNames(array $preparations): array
    {
        $names = [];

        foreach ($preparations as $key => $value) {
            if (is_string($key) && trim($key) !== '') {
                $names[] = $key;

                continue;
            }

            if (is_string($value) && trim($value) !== '') {
                $names[] = $value;

                continue;
            }

            if (is_array($value)) {
                $candidate = $value['name'] ?? null;

                if (is_string($candidate) && trim($candidate) !== '') {
                    $names[] = $candidate;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  array<int|string, mixed>  $components
     * @return array<int, array{name: string, quantity: float, unit: MeasurementUnit}>
     */
    private function normalizeMenuComponents(array $components, bool $forPreparations): array
    {
        $normalized = [];

        foreach ($components as $component) {
            $name = null;
            $unit = null;
            $quantity = 1.0;

            if (is_string($component)) {
                $name = $component;
            } elseif (is_array($component)) {
                $name = $component['name'] ?? null;
                $unit = $component['unit'] ?? null;
                if (array_key_exists('quantity', $component)) {
                    $quantity = (float) $component['quantity'];
                }
            }

            if (! is_string($name) || trim($name) === '') {
                continue;
            }

            if ($forPreparations) {
                $unit = $unit ?? MeasurementUnit::UNIT;
            } else {
                $definition = self::INGREDIENTS[$name] ?? null;
                $unit = $unit ?? ($definition['unit'] ?? MeasurementUnit::UNIT);
            }

            if (! $unit instanceof MeasurementUnit) {
                try {
                    $unit = MeasurementUnit::from($unit);
                } catch (\ValueError) {
                    $unit = MeasurementUnit::UNIT;
                }
            }

            $normalized[] = [
                'name' => $name,
                'quantity' => max(0.0, $quantity),
                'unit' => $unit,
            ];
        }

        return $normalized;
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
                    'allergens' => $meta['allergens'] ?? [],
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

            $ingredient->locations()->syncWithoutDetaching([
                $location->id => ['quantity' => isset($meta['stock']) ? (float) $meta['stock'] : 0.0],
            ]);

            $this->ingredientLocations[$name] = $location->id;

            $ingredients[$name] = $ingredient;
        }

        return $ingredients;
    }

    /**
     * @param  array<string, Ingredient>  $ingredients
     */
    private function seedIngredientStockMovements(array $ingredients): void
    {
        foreach ($ingredients as $ingredient) {
            $ingredient->loadMissing('locations');

            foreach ($ingredient->locations as $location) {
                $currentQuantity = (float) ($location->pivot->quantity ?? 0.0);

                if ($currentQuantity <= 0.01) {
                    continue;
                }

                $alreadyExists = StockMovement::query()
                    ->where('trackable_id', $ingredient->id)
                    ->where('trackable_type', Ingredient::class)
                    ->where('location_id', $location->id)
                    ->exists();

                if ($alreadyExists) {
                    continue;
                }

                $baseDate = CarbonImmutable::now()->subDays(10);
                $initial = round(max($currentQuantity * 0.4, 0.5), 2);
                $afterInitial = $initial;

                $this->createIngredientMovement(
                    $ingredient,
                    $location->id,
                    'addition',
                    0.0,
                    $afterInitial,
                    $baseDate,
                    'Réception fournisseur'
                );

                $restock = round(max($currentQuantity * 0.35, 0.3), 2);
                $afterRestock = round($afterInitial + $restock, 2);

                $this->createIngredientMovement(
                    $ingredient,
                    $location->id,
                    'addition',
                    $afterInitial,
                    $afterRestock,
                    $baseDate->addDays(2),
                    'Réassort cuisine'
                );

                if (abs($afterRestock - $currentQuantity) <= 0.01) {
                    continue;
                }

                $type = $currentQuantity >= $afterRestock ? 'addition' : 'withdrawal';

                $this->createIngredientMovement(
                    $ingredient,
                    $location->id,
                    $type,
                    $afterRestock,
                    $currentQuantity,
                    $baseDate->addDays(5),
                    $type === 'withdrawal' ? 'Préparations du service' : 'Complément urgent'
                );
            }
        }
    }

    private function createIngredientMovement(
        Ingredient $ingredient,
        int $locationId,
        string $type,
        float $before,
        float $after,
        CarbonImmutable $timestamp,
        ?string $reason = null
    ): void {
        $quantity = round(abs($after - $before), 2);

        if ($quantity <= 0.0) {
            return;
        }

        $movement = StockMovement::query()->create([
            'trackable_id' => $ingredient->id,
            'trackable_type' => Ingredient::class,
            'location_id' => $locationId,
            'company_id' => $ingredient->company_id,
            'user_id' => Auth::id(),
            'type' => $type,
            'reason' => $reason,
            'quantity' => $quantity,
            'quantity_before' => round($before, 2),
            'quantity_after' => round($after, 2),
        ]);

        $movement->forceFill([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->saveQuietly();
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

        if ($this->images->exists(self::DEFAULT_PLACEHOLDER_DESTINATION)) {
            return $this->placeholderImagePath = self::DEFAULT_PLACEHOLDER_DESTINATION;
        }

        $localPlaceholder = storage_path('app/'.self::DEFAULT_PLACEHOLDER_SOURCE);
        $localAvailable = is_file($localPlaceholder) && is_readable($localPlaceholder);

        if ($localAvailable) {
            $contents = file_get_contents($localPlaceholder);

            if ($contents === false) {
                $this->command?->warn('Impossible de lire le placeholder local pour la démonstration.');
            } else {
                try {
                    if (Storage::disk('s3')->put(self::DEFAULT_PLACEHOLDER_DESTINATION, $contents)) {
                        return $this->placeholderImagePath = self::DEFAULT_PLACEHOLDER_DESTINATION;
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

    private function menuPlaceholderPath(): string
    {
        if ($this->menuPlaceholderImagePath) {
            return $this->menuPlaceholderImagePath;
        }

        if ($this->images->exists(self::MENU_PLACEHOLDER_DESTINATION)) {
            return $this->menuPlaceholderImagePath = self::MENU_PLACEHOLDER_DESTINATION;
        }

        foreach (self::MENU_PLACEHOLDER_SOURCES as $candidate) {
            $localPath = storage_path('app/'.$candidate);

            if (! is_file($localPath) || ! is_readable($localPath)) {
                continue;
            }

            $contents = file_get_contents($localPath);

            if ($contents === false) {
                continue;
            }

            try {
                if (Storage::disk('s3')->put(self::MENU_PLACEHOLDER_DESTINATION, $contents)) {
                    return $this->menuPlaceholderImagePath = self::MENU_PLACEHOLDER_DESTINATION;
                }
            } catch (Throwable $exception) {
                $this->command?->warn('Impossible de stocker le placeholder menu à partir de '.$candidate.' : '.$exception->getMessage());
            }
        }

        return $this->menuPlaceholderImagePath = $this->placeholderPath();
    }

    private function resolveMenuImageById(int $menuId): ?string
    {
        $localDisk = Storage::disk('local');
        $cloudDisk = Storage::disk('s3');

        foreach (self::MENU_IMAGE_DIRECTORIES as $directory) {
            $normalizedDirectory = trim($directory, '/');

            if ($normalizedDirectory === '') {
                continue;
            }

            foreach (self::MENU_IMAGE_EXTENSIONS as $extension) {
                $relativePath = $normalizedDirectory."/{$menuId}.{$extension}";

                if (! $localDisk->exists($relativePath)) {
                    continue;
                }

                $targetPath = str_starts_with($relativePath, 'private/')
                    ? $relativePath
                    : 'private/'.$relativePath;

                try {
                    if (! $cloudDisk->exists($targetPath)) {
                        $contents = $localDisk->get($relativePath);
                        $cloudDisk->put($targetPath, $contents);
                    }
                } catch (Throwable $exception) {
                    $this->command?->warn('Impossible de publier l\'image du menu #'.$menuId.' : '.$exception->getMessage());

                    return 'storage/app/'.$relativePath;
                }

                return $targetPath;
            }
        }

        return null;
    }

    private function resolveMenuServiceType(mixed $serviceType): MenuServiceType
    {
        if ($serviceType instanceof MenuServiceType) {
            return $serviceType;
        }

        if (is_string($serviceType) && $serviceType !== '') {
            $candidate = strtoupper($serviceType);

            try {
                return MenuServiceType::from($candidate);
            } catch (\ValueError) {
                // Ignore invalid value and fall back to default
            }
        }

        return MenuServiceType::PREP;
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
        $preparations = [];
        $imagePath = $this->placeholderPath();

        foreach (array_keys(self::PREPARATION_COMPONENTS) as $name) {
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

            $preparation->locations()->syncWithoutDetaching([
                $preparationLocation->id => ['quantity' => $this->preparationStock($name)],
            ]);

            $this->preparationLocations[$name] = $preparationLocation->id;

            $preparations[$name] = $preparation;
        }

        foreach (self::PREPARATION_COMPONENTS as $name => $definition) {
            $preparation = $preparations[$name] ?? null;

            if (! $preparation instanceof Preparation) {
                continue;
            }

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
                    $child = $preparations[$childName] ?? null;

                    if (! $child instanceof Preparation) {
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
        }

        return $preparations;
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

    private function resolveMenuType(Company $company, string $name): MenuType
    {
        $menuType = MenuType::firstOrCreate(
            [
                'company_id' => $company->id,
                'name' => $name,
            ]
        );

        $position = self::MENU_TYPE_POSITIONS[$name] ?? null;

        if ($position === null) {
            $position = MenuTypePublicOrder::where('company_id', $company->id)->max('position');
            $position = is_numeric($position) ? ((int) $position) + 1 : 0;
        }

        $menuType->publicOrder()->updateOrCreate(
            ['company_id' => $company->id],
            ['position' => $position]
        );

        return $menuType;
    }

    /**
     * @param  array<string, MenuCategory>  $menuCategories
     * @param  array<string, Ingredient>  $ingredients
     * @param  array<string, Preparation>  $preparations
     * @param  array<string, array<int, array{
     *     name: string,
     *     price: float,
     *     description: ?string,
     *     service_type: string,
     *     is_returnable: bool,
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
        $menuTypeCounters = [];

        foreach ($dataset as $section => $entries) {
            $menuCategory = $menuCategories[$section] ?? null;
            $menuTypeName = self::MENU_TYPE_MAP[$section] ?? 'Plats';
            $menuType = $this->resolveMenuType($company, $menuTypeName);
            $menuTypeId = $menuType->id;
            $menuTypeCounters[$menuTypeId] = $menuTypeCounters[$menuTypeId] ?? 0;

            foreach ($entries as $entry) {
                $priority = $menuTypeCounters[$menuTypeId];
                $menuTypeCounters[$menuTypeId]++;

                $menu = Menu::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'name' => $entry['name'],
                    ],
                    [
                        'description' => $entry['description'] ?? null,
                        'is_a_la_carte' => true,
                        'menu_type_id' => $menuTypeId,
                        'public_priority' => $priority,
                        'price' => $entry['price'],
                        'service_type' => $this->resolveMenuServiceType($entry['service_type'] ?? null)->value,
                        'is_returnable' => (bool) ($entry['is_returnable'] ?? false),
                    ]
                );

                $imagePath = $this->resolveMenuImageById($menu->id) ?? $menu->image_url;

                if (! $imagePath) {
                    $imagePath = $this->menuPlaceholderPath();
                }

                if ($imagePath !== $menu->image_url) {
                    $menu->update(['image_url' => $imagePath]);
                }

                if ($menuCategory instanceof MenuCategory) {
                    $menu->categories()->syncWithoutDetaching([$menuCategory->id]);
                }

                foreach (($entry['ingredients'] ?? []) as $component) {
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

                foreach (($entry['preparations'] ?? []) as $component) {
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

    private function seedOrders(Company $company): void
    {
        $rooms = $this->ensureRooms($company);
        $tables = $this->ensureTables($company, $rooms);

        if ($tables === []) {
            return;
        }

        Order::query()->where('company_id', $company->id)->delete();

        $menuMap = Menu::query()
            ->where('company_id', $company->id)
            ->get()
            ->keyBy('name');

        /** @var Collection<string, User> $users */
        $users = $company->users()->get()->keyBy('email');
        /** @var User|null $defaultUser */
        $defaultUser = $users->first();

        foreach (self::ORDER_BLUEPRINTS as $definition) {
            $tableLabel = $definition['table'] ?? null;
            if (! is_string($tableLabel) || $tableLabel === '') {
                continue;
            }

            $table = $tables[$tableLabel] ?? null;
            if (! $table instanceof Table) {
                continue;
            }

            $status = $definition['status'] ?? OrderStatus::PENDING;
            if (! $status instanceof OrderStatus) {
                $status = OrderStatus::from((string) $status);
            }

            $timeline = $this->buildOrderTimeline($status, $definition['timeline'] ?? []);

            $userEmail = $definition['user'] ?? null;
            $user = is_string($userEmail) && $userEmail !== ''
                ? ($users->get($userEmail) ?? null)
                : null;

            if (! $user instanceof User) {
                $user = $defaultUser;
            }

            if (! $user instanceof User) {
                continue;
            }

            $order = Order::query()->create([
                'company_id' => $company->id,
                'table_id' => $table->id,
                'user_id' => $user->id,
                'status' => $status,
                'pending_at' => $timeline['pending_at'],
                'served_at' => $timeline['served_at'],
                'payed_at' => $timeline['payed_at'],
                'canceled_at' => $timeline['canceled_at'],
            ]);

            $this->seedOrderSteps($order, $definition['steps'] ?? [], $menuMap, $timeline);
        }
    }

    /**
     * @return array<string, Room>
     */
    private function ensureRooms(Company $company): array
    {
        $rooms = [];

        foreach (self::ROOM_BLUEPRINTS as $definition) {
            $code = $definition['code'] ?? null;
            if (! is_string($code) || $code === '') {
                continue;
            }

            $room = Room::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'code' => $code,
                ],
                [
                    'name' => $definition['name'] ?? $code,
                ]
            );

            $rooms[$code] = $room;
        }

        return $rooms;
    }

    /**
     * @param  array<string, Room>  $rooms
     * @return array<string, Table>
     */
    private function ensureTables(Company $company, array $rooms): array
    {
        $tables = [];

        foreach (self::TABLE_BLUEPRINTS as $definition) {
            $label = $definition['label'] ?? null;
            $roomCode = $definition['room_code'] ?? null;

            if (! is_string($label) || $label === '' || ! is_string($roomCode) || $roomCode === '') {
                continue;
            }

            $room = $rooms[$roomCode] ?? null;

            if (! $room instanceof Room) {
                continue;
            }

            $table = Table::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'label' => $label,
                ],
                [
                    'room_id' => $room->id,
                    'seats' => max(1, (int) ($definition['seats'] ?? 2)),
                ]
            );

            $tables[$label] = $table;
        }

        return $tables;
    }

    /**
     * @param  array<string, int>  $timelineDefinition
     * @return array{
     *     pending_at: CarbonImmutable,
     *     served_at: CarbonImmutable|null,
     *     payed_at: CarbonImmutable|null,
     *     canceled_at: CarbonImmutable|null,
     * }
     */
    private function buildOrderTimeline(OrderStatus $status, array $timelineDefinition): array
    {
        $minutesAgo = max(5, (int) ($timelineDefinition['minutes_ago'] ?? 60));
        $pendingAt = CarbonImmutable::now()->subMinutes($minutesAgo);

        $servedAt = null;
        $payedAt = null;
        $canceledAt = null;

        if (in_array($status, [OrderStatus::SERVED, OrderStatus::PAYED], true)) {
            $servedDelay = max(1, (int) ($timelineDefinition['served_after'] ?? 30));
            $servedAt = $pendingAt->addMinutes($servedDelay);
        }

        if ($status === OrderStatus::PAYED) {
            $reference = $servedAt ?? $pendingAt;
            $payedDelay = max(1, (int) ($timelineDefinition['payed_after'] ?? 20));
            $payedAt = $reference->addMinutes($payedDelay);
        }

        if ($status === OrderStatus::CANCELED) {
            $canceledDelay = max(1, (int) ($timelineDefinition['canceled_after'] ?? 15));
            $canceledAt = $pendingAt->addMinutes($canceledDelay);
        }

        return [
            'pending_at' => $pendingAt,
            'served_at' => $servedAt,
            'payed_at' => $payedAt,
            'canceled_at' => $canceledAt,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @param  array{
     *     pending_at: CarbonImmutable,
     *     served_at: CarbonImmutable|null,
     *     payed_at: CarbonImmutable|null,
     *     canceled_at: CarbonImmutable|null,
     * }  $timeline
     */
    private function seedOrderSteps(Order $order, array $steps, Collection $menuMap, array $timeline): void
    {
        $order->steps()->delete();

        /** @var CarbonImmutable $pendingAt */
        $pendingAt = $timeline['pending_at'];

        foreach ($steps as $index => $definition) {
            $status = $definition['status'] ?? OrderStepStatus::IN_PREP;

            if (! $status instanceof OrderStepStatus) {
                $status = OrderStepStatus::from((string) $status);
            }

            $servedAt = null;

            if (array_key_exists('served_after', $definition)) {
                $servedAt = $pendingAt->addMinutes((int) $definition['served_after']);
            } elseif ($status === OrderStepStatus::SERVED) {
                $servedAt = $timeline['served_at'];
            }

            $step = OrderStep::query()->create([
                'order_id' => $order->id,
                'position' => $index + 1,
                'status' => $status,
                'served_at' => $servedAt,
            ]);

            $this->seedStepMenus($step, $definition['menus'] ?? [], $menuMap, $timeline, $status, $servedAt);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $menus
     * @param  array{
     *     pending_at: CarbonImmutable,
     *     served_at: CarbonImmutable|null,
     *     payed_at: CarbonImmutable|null,
     *     canceled_at: CarbonImmutable|null,
     * }  $timeline
     */
    private function seedStepMenus(
        OrderStep $step,
        array $menus,
        Collection $menuMap,
        array $timeline,
        OrderStepStatus $stepStatus,
        ?CarbonImmutable $stepServedAt
    ): void {
        $step->stepMenus()->delete();

        /** @var CarbonImmutable $pendingAt */
        $pendingAt = $timeline['pending_at'];

        foreach ($menus as $menuDefinition) {
            $menuName = $menuDefinition['name'] ?? null;

            if (! is_string($menuName) || trim($menuName) === '') {
                continue;
            }

            $menu = $menuMap->get($menuName);

            if (! $menu instanceof Menu) {
                $this->missingComponents[] = $menuName.' (commande '.$step->order_id.')';

                continue;
            }

            $quantity = max(1, (int) ($menuDefinition['quantity'] ?? 1));
            $statusDefinition = $menuDefinition['status'] ?? null;

            if ($statusDefinition instanceof StepMenuStatus) {
                $menuStatus = $statusDefinition;
            } elseif (is_string($statusDefinition) && $statusDefinition !== '') {
                $menuStatus = StepMenuStatus::from($statusDefinition);
            } else {
                $menuStatus = $this->defaultStepMenuStatus($stepStatus);
            }

            $servedAt = null;

            if (array_key_exists('served_after', $menuDefinition)) {
                $servedAt = $pendingAt->addMinutes((int) $menuDefinition['served_after']);
            } elseif ($menuStatus === StepMenuStatus::SERVED) {
                $servedAt = $stepServedAt ?? $timeline['served_at'];
            }

            StepMenu::query()->create([
                'order_step_id' => $step->id,
                'menu_id' => $menu->id,
                'quantity' => $quantity,
                'status' => $menuStatus,
                'note' => $menuDefinition['note'] ?? null,
                'served_at' => $servedAt,
            ]);
        }
    }

    private function defaultStepMenuStatus(OrderStepStatus $status): StepMenuStatus
    {
        return match ($status) {
            OrderStepStatus::READY => StepMenuStatus::READY,
            OrderStepStatus::SERVED => StepMenuStatus::SERVED,
            default => StepMenuStatus::IN_PREP,
        };
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
