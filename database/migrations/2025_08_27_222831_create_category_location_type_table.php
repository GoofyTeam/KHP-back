<?php

use App\Models\Category;
use App\Models\LocationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_location_type', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Category::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(LocationType::class)->constrained()->cascadeOnDelete();
            $table->unsignedInteger('shelf_life_hours');
            $table->timestamps();

            $table->unique(['category_id', 'location_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_location_type');
    }
};
