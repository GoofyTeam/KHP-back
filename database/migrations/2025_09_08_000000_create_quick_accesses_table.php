<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            // Position 1..5 per company
            $table->unsignedTinyInteger('index');
            $table->string('name', 26);
            $table->string('icon'); // Plus, Notebook, Minus, Calendar, Check
            $table->string('icon_color'); // 'primary' | 'warning' | 'error' | 'info'
            $table->string('url_key');
            $table->timestamps();

            $table->unique(['company_id', 'index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_accesses');
    }
};
