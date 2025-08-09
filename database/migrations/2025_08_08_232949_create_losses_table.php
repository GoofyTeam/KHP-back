<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('losses', function (Blueprint $table) {
            $table->id();

            // Polymorphic fields
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');

            // Localisation
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            // Quantité perdue
            $table->decimal('quantity', 10, 2);

            // Raison
            $table->string('reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('losses');
    }
};
