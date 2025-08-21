<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_preparation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preparation_id')->constrained()->onDelete('cascade')->nullable(false);
            $table->foreignId('category_id')->constrained()->onDelete('cascade')->nullable(false);

            $table->unique(['preparation_id', 'category_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_preparation');
    }
};
