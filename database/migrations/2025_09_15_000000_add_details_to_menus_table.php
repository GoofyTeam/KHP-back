<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('image_url')->nullable()->after('description');
            $table->boolean('is_a_la_carte')->default(false)->after('image_url');
            $table->boolean('is_available')->default(true)->after('is_a_la_carte');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn(['description', 'image_url', 'is_a_la_carte', 'is_available']);
        });
    }
};
