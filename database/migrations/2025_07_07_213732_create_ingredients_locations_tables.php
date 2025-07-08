<?php

use App\Enums\UnitEnum;
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
            $table->enum('unit', UnitEnum::values())->nullable(false);
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
            $table->boolean('use_default_image')->default(false)->nullable(false);
            $table->float('quantity')->default(0)->nullable(false);
            $table->timestamps();
        });

        Schema::create('ingredient_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->onDelete('cascade')->nullable(false);
            $table->foreignId('company_id')->constrained()->onDelete('cascade')->nullable(false);
            $table->string('image_url')->nullable(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_images');
        Schema::dropIfExists('ingredient_location');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('ingredients');
    }
};
