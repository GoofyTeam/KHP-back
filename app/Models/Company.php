<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
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
        });
    }
}
