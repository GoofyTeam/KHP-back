<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreignId('location_id')->after('entity_type')->constrained()->onDelete('cascade');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('auto_complete_menu_orders')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('auto_complete_menu_orders');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });
    }
};
