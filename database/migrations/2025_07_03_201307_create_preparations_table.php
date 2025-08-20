<?php

use App\Enums\MeasurementUnit;
use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('preparations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)
                ->nullable(false)
                ->constrained()
                ->onDelete('cascade');
            $table->string('name')->nullable(false);
            $table->enum('unit', MeasurementUnit::values())
                ->default(MeasurementUnit::UNIT)
                ->nullable(false);
            $table->string('image_url')->nullable(true);
            $table->timestamps();

            $table->unique(['name', 'company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preparations');
    }
};
