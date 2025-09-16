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
use App\Services\ImageService;
use Illuminate\Database\Seeder;
use JsonException;
use Throwable;

class DemoSeeder extends Seeder
{
    private const MENU_DATASET = <<<'JSON'
{
    "hors_doeuvres": [
        {
            "nom": "Notre pâté en croûte, pickles de légumes",
            "prix": 28,
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
            "ingredients": [
                "Sélection de fromages de vache, chèvre, brebis"
            ]
        }
    ],
    "desserts": [
        {
            "nom": "Millefeuille classique à la vanille",
            "prix": 14,
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
        'hors_doeuvres' => "Hors d'œuvres",
        'plats' => 'Plats',
        'fromage' => 'Fromages',
        'desserts' => 'Desserts',
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

    private const INGREDIENT_METADATA = [
    'Farine' => [
        'category' => 'Farines',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '4056489565536',
        'image' => 'https://images.openfoodfacts.org/images/products/405/648/956/5536/front_fr.21.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/4056489565536/farine-de-ble-t45-navarre',
    ],
    'Beurre' => [
        'category' => 'Produits Laitiers',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '26064413',
        'image' => 'https://images.openfoodfacts.org/images/products/000/002/606/4413/front_fr.31.400.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/26064413/beurre-doux-leger-tendre-40-mg-tartine-cuisine-champre',
    ],
    'Eau' => [
        'category' => 'Boissons',
        'unit' => MeasurementUnit::LITRE,
        'barcode' => '1234500001857',
        'image' => 'https://images.openfoodfacts.org/images/products/123/450/000/1857/front_fr.3.400.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/1234500001857/eau',
    ],
    'Sel' => [
        'category' => 'Épicerie',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '10020811',
        'image' => 'https://images.openfoodfacts.org/images/products/000/001/002/0811/front_fr.3.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/10020811/sel-fin-chantesel',
    ],
    'Échine de porc' => [
        'category' => 'Viandes',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '0207024022173',
        'image' => 'https://images.openfoodfacts.org/images/products/020/702/402/2173/front_fr.3.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/0207024022173/echine-de-porc',
    ],
    'Veau' => [
        'category' => 'Viandes',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '2695314012009',
        'image' => 'https://images.openfoodfacts.org/images/products/269/531/401/2009/front_fr.11.400.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/2695314012009/coeur-de-veau',
    ],
    'Foie de volaille' => [
        'category' => 'Viandes',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '0215085018561',
        'image' => 'https://images.openfoodfacts.org/images/products/021/508/501/8561/nutrition_fr.5.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/0215085018561/foies-de-volaille',
    ],
    'Œufs' => [
        'category' => 'Œufs',
        'unit' => MeasurementUnit::UNIT,
        'barcode' => '3560070432080',
        'image' => 'https://images.openfoodfacts.org/images/products/356/007/043/2080/front_fr.38.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3560070432080/oeufs-frais-calibre-moyen-produits-blancs',
    ],
    'Crème' => [
        'category' => 'Produits Laitiers',
        'unit' => MeasurementUnit::LITRE,
        'barcode' => '3258561419299',
        'image' => 'https://images.openfoodfacts.org/images/products/325/856/141/9299/front_fr.9.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3258561419299/creme-fraiche-legere-15-mg-belle-france',
    ],
    'Poivre' => [
        'category' => 'Épices',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '8720254531779',
        'image' => 'https://images.openfoodfacts.org/images/products/872/025/453/1779/front_fr.11.400.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/8720254531779/poivre',
    ],
    'Armagnac' => [
        'category' => 'Spiritueux',
        'unit' => MeasurementUnit::LITRE,
        'barcode' => '3560070575480',
        'image' => 'https://images.openfoodfacts.org/images/products/356/007/057/5480/front_fr.10.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3560070575480/armagnac-saint-merac',
    ],
    'Épices' => [
        'category' => 'Épices',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '3700483800544',
        'image' => 'https://images.openfoodfacts.org/images/products/370/048/380/0544/front_fr.10.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3700483800544/epices-toutes-viandes-mosaique',
    ],
    'Fond de volaille' => [
        'category' => 'Épicerie',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '3256225451647',
        'image' => 'https://images.openfoodfacts.org/images/products/325/622/545/1647/front_fr.40.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3256225451647/fond-de-volaille-u',
    ],
    'Gélatine' => [
        'category' => 'Épicerie',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '3256225731978',
        'image' => 'https://images.openfoodfacts.org/images/products/325/622/573/1978/front_fr.42.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3256225731978/gelatine-alimentaire-9-feuilles-17g-u',
    ],
    'Carottes' => [
        'category' => 'Légumes',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '3596710431151',
        'image' => 'https://images.openfoodfacts.org/images/products/359/671/043/1151/front_fr.33.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3596710431151/carottes-extra-fines-auchan',
    ],
    'Chou-fleur' => [
        'category' => 'Légumes',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '3560070122349',
        'image' => 'https://images.openfoodfacts.org/images/products/356/007/012/2349/front_fr.82.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3560070122349/choux-fleurs-en-fleurettes-carrefour',
    ],
    'Oignons' => [
        'category' => 'Légumes',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '3363290420116',
        'image' => 'https://images.openfoodfacts.org/images/products/336/329/042/0116/front_fr.17.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3363290420116/oignon-jaune-jardins-du-midi',
    ],
    'Cornichons' => [
        'category' => 'Épicerie',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '4061464817722',
        'image' => 'https://images.openfoodfacts.org/images/products/406/146/481/7722/front_de.6.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/4061464817722/cornichons-aldi',
    ],
    'Vinaigre blanc' => [
        'category' => 'Épicerie',
        'unit' => MeasurementUnit::LITRE,
        'barcode' => '3077311522405',
        'image' => 'https://images.openfoodfacts.org/images/products/307/731/152/2405/front_fr.4.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3077311522405/vinaigre-blanc',
    ],
    'Sucre' => [
        'category' => 'Épicerie',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '3596710473557',
        'image' => 'https://images.openfoodfacts.org/images/products/359/671/047/3557/front_fr.59.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3596710473557/sucre-blanc-en-poudre-auchan',
    ],
    'Graines de moutarde' => [
        'category' => 'Épices',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '7610845400434',
        'image' => 'https://images.openfoodfacts.org/images/products/761/084/540/0434/front_fr.6.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/7610845400434/graines-de-moutarde-jaune-coop-qualite-prix',
    ],
    'Foie gras de canard cru' => [
        'category' => 'Viandes',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '26078410',
        'image' => 'https://images.openfoodfacts.org/images/products/000/002/607/8410/front_fr.5.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/26078410/foie-gras-de-canard-cru-excellence',
    ],
    'Lait' => [
        'category' => 'Produits Laitiers',
        'unit' => MeasurementUnit::LITRE,
        'barcode' => '3428272970017',
        'image' => 'https://images.openfoodfacts.org/images/products/342/827/297/0017/front_fr.23.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3428272970017/lait-demi-ecreme-uht-lactel',
    ],
    'Levure de boulanger' => [
        'category' => 'Épicerie',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '2006050036622',
        'image' => 'https://images.openfoodfacts.org/images/products/200/605/003/6622/front_fr.3.400.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/2006050036622/levure-boulangere',
    ],
    'Homard bleu' => [
        'category' => 'Fruits de Mer',
        'unit' => MeasurementUnit::UNIT,
        'barcode' => '3770000648317',
        'image' => 'https://images.openfoodfacts.org/images/products/377/000/064/8317/nutrition_fr.12.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3770000648317/homard-bleu-facon-cardinal',
    ],
    'Haricots verts frais' => [
        'category' => 'Légumes',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '3760086270076',
        'image' => 'https://images.openfoodfacts.org/images/products/376/008/627/0076/front_fr.3.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3760086270076/haricots-verts-frais',
    ],
    'Amandes fraîches' => [
        'category' => 'Fruits secs',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '3700194630287',
        'image' => 'https://images.openfoodfacts.org/images/products/370/019/463/0287/front_fr.4.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3700194630287/amandes-crues-fruidyllic',
    ],
    'Huile d’olive' => [
        'category' => 'Épicerie',
        'unit' => MeasurementUnit::LITRE,
        'barcode' => '3424096003078',
        'image' => 'https://images.openfoodfacts.org/images/products/342/409/600/3078/front_fr.3.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3424096003078/huile-d-olive',
    ],
    'Citron' => [
        'category' => 'Fruits',
        'unit' => MeasurementUnit::UNIT,
        'barcode' => '3256226081881',
        'image' => 'https://images.openfoodfacts.org/images/products/325/622/608/1881/front_fr.20.400.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3256226081881/citron-verna-calibre-4-5-categorie-2-filet-4-fruits-u',
    ],
    'Tomate de plein champ' => [
        'category' => 'Légumes',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '3017800246658',
        'image' => 'https://images.openfoodfacts.org/images/products/377/000/372/5152/front_fr.31.200.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3017800246658/58cl-tomates-entieres-pelees-de-plein-champ-sans-sel-ajoute-bio-d-aucy',
    ],
    'Filets d’anchois' => [
        'category' => 'Poissons',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '3218370591821',
        'image' => 'https://images.openfoodfacts.org/images/products/321/837/059/1821/front_fr.12.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3218370591821/filets-d-anchois-a-l-orientale-miceli',
    ],
    'Basilic frais' => [
        'category' => 'Herbes aromatiques',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '3411061111029',
        'image' => 'https://images.openfoodfacts.org/images/products/006/038/300/1407/front_fr.3.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3411061111029/barq-basilic-frais-20g-cueillettes-et-cuisine',
    ],
    'Dos de bar' => [
        'category' => 'Poissons',
        'unit' => MeasurementUnit::UNIT,
        'barcode' => '3664335055264',
        'image' => 'https://images.openfoodfacts.org/images/products/366/433/505/5264/front_fr.3.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3664335055264/filet-de-bar-l-atelier-poissonnerie',
    ],
    'Courgette trompette' => [
        'category' => 'Légumes',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '2306375001603',
        'image' => 'https://images.openfoodfacts.org/images/products/230/637/500/1603/front_fr.4.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/2306375001603/courgette',
    ],
    'Vin blanc sec' => [
        'category' => 'Boissons',
        'unit' => MeasurementUnit::LITRE,
        'barcode' => '3660989151932',
        'image' => 'https://images.openfoodfacts.org/images/products/366/098/915/1932/front_fr.4.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3660989151932/vin-blanc-sec',
    ],
    'Échalotes' => [
        'category' => 'Légumes',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '8431876150353',
        'image' => 'https://images.openfoodfacts.org/images/products/540/011/900/6200/front_fr.5.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/8431876150353/echalotes-carrefour',
    ],
    'Persil' => [
        'category' => 'Herbes aromatiques',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '2006050101283',
        'image' => 'https://images.openfoodfacts.org/images/products/200/605/010/1283/front_fr.3.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/2006050101283/persil',
    ],
    'Sole' => [
        'category' => 'Poissons',
        'unit' => MeasurementUnit::UNIT,
        'barcode' => '0059749982474',
        'image' => 'https://images.openfoodfacts.org/images/products/005/974/998/2474/front_fr.3.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/0059749982474/filets-de-sole-naturalia',
    ],
    'Jus de citron' => [
        'category' => 'Épicerie',
        'unit' => MeasurementUnit::LITRE,
        'barcode' => '3564700299043',
        'image' => 'https://images.openfoodfacts.org/images/products/000/002/001/9907/front_fr.248.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3564700299043/jus-de-citron-vert-marque-repere',
    ],
    'Artichauts frais' => [
        'category' => 'Légumes',
        'unit' => MeasurementUnit::UNIT,
        'barcode' => '3256220652766',
        'image' => 'https://images.openfoodfacts.org/images/products/325/622/065/2766/front_fr.47.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3256220652766/fonds-d-artichauts-boite-210g-u',
    ],
    'Ail' => [
        'category' => 'Légumes',
        'unit' => MeasurementUnit::UNIT,
        'barcode' => '3256228100191',
        'image' => 'https://images.openfoodfacts.org/images/products/325/622/810/0191/front_fr.13.400.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3256228100191/ail-u',
    ],
    'Sucre glace' => [
        'category' => 'Épicerie',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '3220035730001',
        'image' => 'https://images.openfoodfacts.org/images/products/322/003/573/0001/front_fr.35.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3220035730001/boite-sucre-glace-250g-saint-louis',
    ],
    'Gousse de vanille' => [
        'category' => 'Épices',
        'unit' => MeasurementUnit::UNIT,
        'barcode' => '3256225732043',
        'image' => 'https://images.openfoodfacts.org/images/products/325/622/573/2043/front_fr.28.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3256225732043/gousse-de-vanille-u',
    ],
    'Jaunes d’œuf' => [
        'category' => 'Œufs',
        'unit' => MeasurementUnit::UNIT,
        'barcode' => '3439496001838',
        'image' => 'https://images.openfoodfacts.org/images/products/343/949/600/1838/nutrition_fr.17.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3439496001838/jaunes-d-oeuf-metro',
    ],
    'Fécule' => [
        'category' => 'Farines',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '3347431805482',
        'image' => 'https://images.openfoodfacts.org/images/products/334/743/180/5482/front_fr.15.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3347431805482/fecule-amidon-de-pomme-de-terre-moulin-des-moines',
    ],
    'Pêches' => [
        'category' => 'Fruits',
        'unit' => MeasurementUnit::UNIT,
        'barcode' => '3276559409466',
        'image' => 'https://images.openfoodfacts.org/images/products/005/907/205/0109/front_fr.3.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3276559409466/peches-simply',
    ],
    'Sirop' => [
        'category' => 'Épicerie',
        'unit' => MeasurementUnit::LITRE,
        'barcode' => '5708776000877',
        'image' => 'https://images.openfoodfacts.org/images/products/570/877/600/0877/front_fr.13.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/5708776000877/sirop-d-erable-vertmont',
    ],
    'Vanille' => [
        'category' => 'Épices',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '6133798001790',
        'image' => 'https://images.openfoodfacts.org/images/products/613/379/800/1790/front_fr.3.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/6133798001790/vanille-en-poudre',
    ],
    'Framboises' => [
        'category' => 'Fruits',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '3385630118309',
        'image' => 'https://images.openfoodfacts.org/images/products/338/563/011/8309/front_fr.7.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/3385630118309/framboise-fruits-rouges-co',
    ],
    'Glace vanille' => [
        'category' => 'Desserts',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '26048154',
        'image' => 'https://images.openfoodfacts.org/images/products/000/002/604/8154/front_fr.34.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/26048154/glace-vanille-mucci',
    ],
    'Sélection de fromages de vache, chèvre, brebis' => [
        'category' => 'Fromages',
        'unit' => MeasurementUnit::GRAM,
        'barcode' => '0200340018370',
        'image' => 'https://images.openfoodfacts.org/images/products/020/034/001/8370/front_fr.4.full.jpg',
        'source_url' => 'https://world.openfoodfacts.org/product/0200340018370/itchebai-chevre-brebis',
    ],
    ];

    private ImageService $images;

    /** @var array<int, string> */
    private array $missingIngredients = [];

    /** @var array<int, string> */
    private array $missingComponents = [];

    /** @var array<int, string> */
    private array $missingImages = [];

    public function __construct(ImageService $images)
    {
        $this->images = $images;
    }

    public function run(): void
    {
        try {
            $dataset = json_decode(self::MENU_DATASET, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Invalid JSON dataset for DemoSeeder', 0, $exception);
        }

        $company = Company::firstOrCreate(
            ['name' => 'Demo Bistro'],
            ['open_food_facts_language' => 'fr']
        );

        $defaultLocation = $this->resolveDefaultLocation($company);
        $categories = $this->ensureCategories($company);
        $ingredients = $this->seedIngredients($company, $categories, $defaultLocation);
        $preparations = $this->seedPreparations(
            $company,
            $categories['Préparations Maison'],
            $defaultLocation,
            $ingredients
        );
        $menuCategories = $this->ensureMenuCategories($company);
        $normalisedDataset = $this->normaliseDataset($dataset);
        $this->seedMenus($company, $normalisedDataset, $menuCategories, $ingredients, $preparations, $defaultLocation);

        $this->report();
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $raw
     * @return array<string, array<int, array{name: string, price: float, ingredients: array<int, string>, preparations: array<int, string>}>>
     */
    private function normaliseDataset(array $raw): array
    {
        $result = [];

        foreach ($raw as $section => $items) {
            $result[$section] = [];
            foreach ($items as $entry) {
                $result[$section][] = [
                    'name' => $entry['nom'],
                    'price' => (float) ($entry['prix'] ?? 0),
                    'ingredients' => array_map('trim', $entry['ingredients'] ?? []),
                    'preparations' => array_keys($entry['preparations'] ?? []),
                ];
            }
        }

        return $result;
    }

    private function resolveDefaultLocation(Company $company): Location
    {
        $location = $company->locations()->firstWhere('name', 'Réfrigérateur')
            ?? $company->locations()->first();

        if ($location instanceof Location) {
            return $location;
        }

        $type = $company->locationTypes()->firstWhere('name', 'Réfrigérateur')
            ?? $company->locationTypes()->first();

        return $company->locations()->create([
            'name' => 'Réfrigérateur Démo',
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
            self::INGREDIENT_METADATA
        );
        $names[] = 'Préparations Maison';
        $names[] = 'Ingrédients Divers';
        $names = array_values(array_unique($names));

        $categories = [];
        foreach ($names as $name) {
            $category = Category::firstOrCreate([
                'company_id' => $company->id,
                'name' => $name,
            ]);

            $categories[$name] = $category->id;
        }

        return $categories;
    }

    /**
     * @return array<string, Ingredient>
     */
    private function seedIngredients(Company $company, array $categories, Location $defaultLocation): array
    {
        $ingredients = [];

        foreach (self::INGREDIENT_METADATA as $name => $meta) {
            $categoryName = $meta['category'] ?? 'Ingrédients Divers';
            $categoryId = $categories[$categoryName] ?? $categories['Ingrédients Divers'];
            $unit = $meta['unit'] ?? MeasurementUnit::UNIT;

            $imagePath = null;
            if (! empty($meta['image'])) {
                try {
                    $imagePath = $this->images->storeFromUrl($meta['image'], 'ingredients');
                } catch (Throwable $exception) {
                    $this->missingImages[] = $name.' ('.$meta['image'].')';
                }
            }

            if (! $imagePath) {
                try {
                    $imagePath = $this->images->storePlaceholder();
                } catch (Throwable $exception) {
                    $imagePath = null;
                }
            }

            $ingredient = Ingredient::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $name,
                ],
                [
                    'category_id' => $categoryId,
                    'image_url' => $imagePath,
                    'unit' => $unit->value,
                    'base_quantity' => 0,
                    'base_unit' => $unit->value,
                    'barcode' => $meta['barcode'] ?? null,
                    'allergens' => [],
                ]
            );

            $ingredient->locations()->syncWithoutDetaching([
                $defaultLocation->id => ['quantity' => 0],
            ]);

            $ingredients[$name] = $ingredient;
        }

        return $ingredients;
    }

    /**
     * @param array<string, Ingredient> $ingredients
     * @return array<string, Preparation>
     */
    private function seedPreparations(
        Company $company,
        int $preparationCategoryId,
        Location $defaultLocation,
        array $ingredients
    ): array {
        $cache = [];

        foreach (array_keys(self::PREPARATION_COMPONENTS) as $name) {
            $this->buildPreparation(
                $name,
                $company,
                $preparationCategoryId,
                $defaultLocation,
                $ingredients,
                $cache
            );
        }

        return $cache;
    }

    /**
     * @param array<string, Ingredient> $ingredients
     * @param array<string, Preparation> $cache
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
     * @param array<string, MenuCategory> $menuCategories
     * @param array<string, Ingredient> $ingredients
     * @param array<string, Preparation> $preparations
     * @param array<string, array<int, array{name: string, price: float, ingredients: array<int, string>, preparations: array<int, string>}>> $dataset
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

    /**
     * @return array<string, MenuCategory>
     */
    private function ensureMenuCategories(Company $company): array
    {
        $result = [];

        foreach (self::MENU_CATEGORY_LABELS as $key => $label) {
            $result[$key] = MenuCategory::firstOrCreate([
                'company_id' => $company->id,
                'name' => $label,
            ]);
        }

        return $result;
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
