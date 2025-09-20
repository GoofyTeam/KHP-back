<?php

use App\Enums\MeasurementUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('entity_id');
            $table->string('entity_type');
            $table->decimal('quantity', 8, 2);
            $table->enum('unit', MeasurementUnit::values())->default(MeasurementUnit::UNIT->value);
            $table->timestamps();
            $table->unique(['menu_id', 'entity_id', 'entity_type']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
