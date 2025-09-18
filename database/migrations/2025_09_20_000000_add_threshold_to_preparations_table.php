<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preparations', function (Blueprint $table) {
            $table->decimal('threshold', 8, 2)->nullable()->after('base_unit');
        });
    }

    public function down(): void
    {
        Schema::table('preparations', function (Blueprint $table) {
            $table->dropColumn('threshold');
        });
    }
};
