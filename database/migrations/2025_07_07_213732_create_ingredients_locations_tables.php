<?php

use App\Enums\MeasurementUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(false);
            $table->foreignId('company_id')->constrained()->onDelete('cascade')->nullable(false);
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('image_url')->nullable(true);
            $table->enum('unit', MeasurementUnit::values())
                ->default(MeasurementUnit::UNIT)
                ->nullable(false);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
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

            $table->unique(['ingredient_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_location');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('ingredients');
    }
};
