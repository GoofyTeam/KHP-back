<?php

use App\Enums\MeasurementUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preparation_entities', function (Blueprint $table) {
            $table->foreignId('location_id')->after('entity_type')->constrained()->onDelete('cascade');
            $table->decimal('quantity', 8, 2)->after('location_id');
            $table->enum('unit', MeasurementUnit::values())->default(MeasurementUnit::UNIT->value)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('preparation_entities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn('quantity');
            $table->dropColumn('unit');
        });
    }
};
