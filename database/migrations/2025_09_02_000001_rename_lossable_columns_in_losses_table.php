<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('losses', function (Blueprint $table) {
            // Add new columns (nullable during migration to allow data copy)
            $table->unsignedBigInteger('loss_item_id')->nullable()->after('id');
            $table->string('loss_item_type')->nullable()->after('loss_item_id');
        });

        // Copy existing data into the new columns
        DB::table('losses')->update([
            'loss_item_id' => DB::raw('lossable_id'),
            'loss_item_type' => DB::raw('lossable_type'),
        ]);

        // Create an index equivalent to the original morphs index
        Schema::table('losses', function (Blueprint $table) {
            $table->index(['loss_item_type', 'loss_item_id']);
        });

        // Drop the old index and columns (use columns to infer correct index name)
        Schema::table('losses', function (Blueprint $table) {
            $table->dropIndex(['lossable_type', 'lossable_id']);
            $table->dropColumn(['lossable_id', 'lossable_type']);
        });

        // Optionally enforce NOT NULL on new columns (skipped to avoid DBAL requirements)
    }

    public function down(): void
    {
        Schema::table('losses', function (Blueprint $table) {
            $table->unsignedBigInteger('lossable_id')->nullable()->after('id');
            $table->string('lossable_type')->nullable()->after('lossable_id');
        });

        DB::table('losses')->update([
            'lossable_id' => DB::raw('loss_item_id'),
            'lossable_type' => DB::raw('loss_item_type'),
        ]);

        Schema::table('losses', function (Blueprint $table) {
            $table->index(['lossable_type', 'lossable_id']);
        });

        Schema::table('losses', function (Blueprint $table) {
            $table->dropIndex(['loss_item_type', 'loss_item_id']);
            $table->dropColumn(['loss_item_id', 'loss_item_type']);
        });
    }
};
