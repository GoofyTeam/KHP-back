<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->string('type')->after('is_available');
            $table->decimal('price', 8, 2)->default(0)->after('type');
        });

        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('menu_category_menu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->onDelete('cascade');
            $table->foreignId('menu_category_id')->constrained('menu_categories')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_category_menu');
        Schema::dropIfExists('menu_categories');

        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn(['type', 'price']);
        });
    }
};
