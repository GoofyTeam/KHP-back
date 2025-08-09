<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLossesTable extends Migration
{
    public function up()
    {
        Schema::create('losses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // polymorphic
            $table->morphs('ingredient');
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->string('unit')->nullable(); // optional unit (g, ml, portion...)
            $table->string('reason')->nullable(); // enum-like key (from config)
            $table->text('comment')->nullable(); // libre pour details
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('losses');
    }
}
