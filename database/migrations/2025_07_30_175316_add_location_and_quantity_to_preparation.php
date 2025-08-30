<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_preparation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preparation_id')->constrained()->onDelete('cascade')->nullable(false);
            $table->foreignId('location_id')->constrained()->onDelete('cascade')->nullable(false);
            $table->decimal('quantity', 8, 2)->default(0)->nullable(false);
            $table->timestamps();

            $table->unique(['preparation_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_preparation');
    }
};
