<?php

use App\Enums\PreparationTypeEnum;
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
            $table->string('unit')->nullable(false);
            $table->enum('type', PreparationTypeEnum::values())->nullable(false);
            $table->timestamps();

            // Ajouter une contrainte d'unicité composite sur name + company_id
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
