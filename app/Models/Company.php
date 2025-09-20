<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property string $open_food_facts_language
 * @property string $public_menu_card_url
 * @property bool $show_out_of_stock_menus_on_card
 * @property bool $show_menu_images
 */
class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'open_food_facts_language',
        'public_menu_card_url',
        'show_out_of_stock_menus_on_card',
        'show_menu_images',
    ];

    protected $casts = [
        'open_food_facts_language' => 'string',
        'public_menu_card_url' => 'string',
        'show_out_of_stock_menus_on_card' => 'bool',
        'show_menu_images' => 'bool',
    ];

    protected $attributes = [
        'show_out_of_stock_menus_on_card' => false,
        'show_menu_images' => true,
    ];

    /**
     * Get the users associated with the company.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the preparations associated with the company.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function preparations()
    {
        return $this->hasMany(Preparation::class);
    }

    /**
     * Get the locations associated with the company.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function locations()
    {
        return $this->hasMany(Location::class);
    }

    /**
     * Get the ingredients associated with the company.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function ingredients()
    {
        return $this->hasMany(Ingredient::class);
    }

    /**
     * Get the categories associated with the company.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get the menus belonging to the company.
     */
    public function menus()
    {
        return $this->hasMany(Menu::class);
    }

    public function locationTypes()
    {
        return $this->hasMany(LocationType::class);
    }

    public function lossReasons()
    {
        return $this->hasMany(LossReason::class);
    }

    public function quickAccesses()
    {
        return $this->hasMany(QuickAccess::class);
    }

    // Removed: specialQuickAccess relation (merged into QuickAccess index 5)

    public function getPublicMenuSettingsAttribute(): array
    {
        return [
            'public_menu_card_url' => $this->public_menu_card_url,
            'show_out_of_stock_menus_on_card' => $this->show_out_of_stock_menus_on_card,
            'show_menu_images' => $this->show_menu_images,
        ];
    }

    protected static function booted()
    {
        static::creating(function ($company) {
            if (! $company->public_menu_card_url) {
                $company->public_menu_card_url = 'temp-'.Str::uuid();
            }
        });

        static::created(function ($company) {
            if (! $company->public_menu_card_url || str_starts_with($company->public_menu_card_url, 'temp-')) {
                $company->public_menu_card_url = sprintf('%d-%s', $company->id, Str::slug($company->name));
                $company->saveQuietly();
            }

            // Créer les types de localisation par défaut
            $defaultTypes = [
                ['name' => 'Congélateur', 'is_default' => true],
                ['name' => 'Réfrigérateur', 'is_default' => true],
                ['name' => 'Autre', 'is_default' => true],
            ];

            $locationTypes = $company->locationTypes()->createMany($defaultTypes);

            // Créer les localisations par défaut associées aux types
            $company->locations()->create([
                'name' => 'Congélateur',
                'location_type_id' => $locationTypes[0]->id,
            ]);

            $company->locations()->create([
                'name' => 'Réfrigérateur',
                'location_type_id' => $locationTypes[1]->id,
            ]);

            // Créer les raisons de perte par défaut
            $company->lossReasons()->createMany(array_map(fn ($name) => ['name' => $name], [
                'Expired',
                'Broken',
                'Spilled',
                'Contaminated',
                'Damaged',
                'Lost',
                'Other',
            ]));

            // Créer les boutons de quick access par défaut
            $defaults = [
                [
                    'index' => 1,
                    'name' => 'Add to stock',
                    'icon' => 'Plus',
                    'icon_color' => 'primary',
                    'url_key' => 'add_to_stock',
                ],
                [
                    'index' => 2,
                    'name' => 'Menu Card',
                    'icon' => 'Notebook',
                    'icon_color' => 'info',
                    'url_key' => 'menu_card',
                ],
                [
                    'index' => 3,
                    'name' => 'Stock',
                    'icon' => 'Check',
                    'icon_color' => 'primary',
                    'url_key' => 'stock',
                ],
                [
                    'index' => 4,
                    'name' => 'Take Order',
                    'icon' => 'Notebook',
                    'icon_color' => 'primary',
                    'url_key' => 'take_order',
                ],
                [
                    'index' => 5,
                    'name' => 'Move Quantity',
                    'icon' => 'NoIcon',
                    'icon_color' => 'info',
                    'url_key' => 'move_quantity',
                ],
            ];

            foreach ($defaults as $row) {
                $company->quickAccesses()->updateOrCreate(
                    ['index' => $row['index']],
                    [
                        'name' => $row['name'],
                        'icon' => $row['icon'],
                        'icon_color' => $row['icon_color'],
                        'url_key' => $row['url_key'],
                    ]
                );
            }
        });
    }
}
