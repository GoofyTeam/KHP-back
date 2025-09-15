<?php

use App\Enums\MeasurementUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preparations', function (Blueprint $table) {
            $table->decimal('base_quantity', 8, 2)->default(1)->after('unit');
            $table->enum('base_unit', MeasurementUnit::values())->default(MeasurementUnit::UNIT->value)->after('base_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('preparations', function (Blueprint $table) {
            $table->dropColumn('base_quantity');
            $table->dropColumn('base_unit');
        });
    }
};
