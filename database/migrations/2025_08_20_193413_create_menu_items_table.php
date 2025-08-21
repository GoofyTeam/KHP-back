<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->onDelete('cascade');

            // Polymorphisme : Ingredient ou Preparation
            $table->string('entity_type'); // App\Models\Ingredient ou App\Models\Preparation
            $table->unsignedBigInteger('entity_id');

            $table->float('quantity');

            // Unité issue de l'enum MeasurementUnit
            $table->string('unit')->default(\App\Enums\MeasurementUnit::UNIT->value);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
