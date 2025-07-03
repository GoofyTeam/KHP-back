<?php

use App\Enums\UnitEnum;
use App\Models\Company;
use App\Enums\PreparationTypeEnum;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
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
                ->onDelete('cascade')
                ->after('id');
            $table->text('name')->unique();
            $table->enum('unit', UnitEnum::values())->nullable(false);
            $table->enum('type', PreparationTypeEnum::values())->nullable(false);
            $table->timestamps();
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
