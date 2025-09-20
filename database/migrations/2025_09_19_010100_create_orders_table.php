<?php

use App\Enums\OrderStatus;
use App\Enums\OrderStepStatus;
use App\Enums\StepMenuStatus;
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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('table_id')->constrained('tables')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('status', OrderStatus::values())->nullable(false);

            $table->timestamp('pending_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamp('payed_at')->nullable();
            $table->timestamp('canceled_at')->nullable();

            $table->timestamps();
        });

        Schema::create('order_steps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('position')->default(0);
            $table->enum('status', OrderStepStatus::values());
            $table->timestamp('served_at')->nullable();

            $table->timestamps();
        });

        Schema::create('step_menus', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_step_id')->constrained('order_steps')->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('quantity')->default(1);
            $table->enum('status', StepMenuStatus::values());
            $table->text('note')->nullable();
            $table->timestamp('served_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('step_menus');
        Schema::dropIfExists('order_steps');
        Schema::dropIfExists('orders');
    }
};
