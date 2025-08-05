<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(false);
            $table->foreignId('company_id')->constrained()->onDelete('cascade')->nullable(false);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(false);
            $table->foreignId('company_id')->constrained()->onDelete('cascade')->nullable(false);
            $table->string('image_url')->nullable(true);
            $table->string('unit')->nullable(false);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('category_ingredient', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->onDelete('cascade')->nullable(false);
            $table->foreignId('category_id')->constrained()->onDelete('cascade')->nullable(false);
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(false);
            $table->foreignId('company_id')->constrained()->onDelete('cascade')->nullable(false);
            $table->timestamps();
        });

        Schema::create('ingredient_location', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->onDelete('cascade')->nullable(false);
            $table->foreignId('location_id')->constrained()->onDelete('cascade')->nullable(false);
            $table->decimal('quantity', 8, 2)->default(0)->nullable(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_location');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('category_ingredient');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('categories');
    }
};
