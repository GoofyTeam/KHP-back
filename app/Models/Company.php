<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property bool $auto_complete_menu_orders
 * @property string $open_food_facts_language
 */
class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'auto_complete_menu_orders',
        'open_food_facts_language',
    ];

    protected $casts = [
        'auto_complete_menu_orders' => 'bool',
        'open_food_facts_language' => 'string',
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

    public function specialQuickAccess()
    {
        return $this->hasOne(SpecialQuickAccess::class);
    }

    protected static function booted()
    {
        static::created(function ($company) {
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
        });
    }
}
