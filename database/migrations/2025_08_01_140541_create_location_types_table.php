<?php

use App\Models\Company;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('location_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(false);
            $table->foreignId('company_id')->constrained()->onDelete('cascade')->nullable(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->foreignId('location_type_id')
                ->nullable()
                ->after('company_id')
                ->constrained()
                ->onDelete('set null');
        });

        // Ajouter les types par défaut pour les compagnies existantes
        $this->createDefaultLocationTypes();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropForeign(['location_type_id']);
            $table->dropColumn('location_type_id');
        });

        Schema::dropIfExists('location_types');
    }

    /**
     * Crée les types de localisation par défaut pour toutes les compagnies existantes
     */
    private function createDefaultLocationTypes(): void
    {
        $companies = Company::all();
        $defaultTypes = ['Congélateur', 'Réfrigérateur', 'Autre'];

        foreach ($companies as $company) {
            $typeIds = [];

            // Création des types par défaut
            foreach ($defaultTypes as $type) {
                $typeId = DB::table('location_types')->insertGetId([
                    'name' => $type,
                    'company_id' => $company->id,
                    'is_default' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $typeIds[$type] = $typeId;
            }

            // Association des locations existantes à leurs types
            $locations = Location::where('company_id', $company->id)->get();

            foreach ($locations as $location) {
                $typeName = 'Autre';

                if ($location->name === 'Congélateur') {
                    $typeName = 'Congélateur';
                } elseif ($location->name === 'Réfrigérateur') {
                    $typeName = 'Réfrigérateur';
                }

                $location->location_type_id = $typeIds[$typeName];
                $location->save();
            }
        }
    }
};
