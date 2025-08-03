<?php

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
        Schema::create('preparation_entities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('preparation_id')
                ->nullable(false)
                ->constrained('preparations')
                ->onDelete('cascade');

            $table->unsignedBigInteger('entity_id')->nullable(false);

            $table->string('entity_type')->nullable(false);

            $table->timestamps();

            $table->unique(['preparation_id', 'entity_id', 'entity_type'], 'unique_preparation_relation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preparation_entities');
    }
};
