<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'public_menu_card_url')) {
                $table->string('public_menu_card_url', 255)
                    ->default(fn () => sprintf('temp-%s', Str::orderedUuid()))
                    ->unique();
            }

            if (! Schema::hasColumn('companies', 'show_out_of_stock_menus_on_card')) {
                $table->boolean('show_out_of_stock_menus_on_card')->default(false);
            }

            if (! Schema::hasColumn('companies', 'show_menu_images')) {
                $table->boolean('show_menu_images')->default(true);
            }
        });

        DB::table('companies')
            ->select('id', 'name', 'public_card_url', 'public_menu_card_url')
            ->orderBy('id')
            ->lazy()
            ->each(function ($company) {
                if (! empty($company->public_menu_card_url)) {
                    return;
                }

                $slugSource = $company->public_card_url ?: (Str::slug($company->name) ?: (string) Str::orderedUuid());

                DB::table('companies')
                    ->where('id', $company->id)
                    ->update([
                        'public_menu_card_url' => sprintf('%d-%s', $company->id, $slugSource),
                    ]);
            });

        if (Schema::hasColumn('companies', 'public_card_url')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('public_card_url');
            });
        }
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('public_card_url', 255)->nullable();
            $table->dropColumn(['public_menu_card_url', 'show_out_of_stock_menus_on_card', 'show_menu_images']);
        });
    }
};
