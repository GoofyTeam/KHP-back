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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->morphs('trackable');
            $table->foreignId('location_id')->constrained();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->enum('type', ['addition', 'withdrawal'])->comment('Type de mouvement: ajout ou retrait');
            $table->decimal('quantity', 8, 2);
            $table->decimal('quantity_before', 8, 2)->nullable();
            $table->decimal('quantity_after', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
