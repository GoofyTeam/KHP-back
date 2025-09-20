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
                    ->nullable()
                    ->unique();
            }

            if (! Schema::hasColumn('companies', 'show_out_of_stock_menus_on_card')) {
                $table->boolean('show_out_of_stock_menus_on_card')->default(false);
            }

            if (! Schema::hasColumn('companies', 'show_menu_images')) {
                $table->boolean('show_menu_images')->default(true);
            }
        });

        $hasPublicCardUrl = Schema::hasColumn('companies', 'public_card_url');

        $columns = ['id', 'name'];

        if ($hasPublicCardUrl) {
            $columns[] = 'public_card_url';
        }

        $columns[] = 'public_menu_card_url';

        DB::table('companies')
            ->select($columns)
            ->orderBy('id')
            ->lazy()
            ->each(function ($company) use ($hasPublicCardUrl) {
                if (! empty($company->public_menu_card_url)) {
                    return;
                }

                $slugSource = $hasPublicCardUrl && ! empty($company->public_card_url)
                    ? $company->public_card_url
                    : (Str::slug($company->name) ?: (string) Str::orderedUuid());

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

        if (Schema::hasColumn('companies', 'public_menu_card_url')) {
            $driver = DB::getDriverName();

            $statements = [
                'pgsql' => 'ALTER TABLE companies ALTER COLUMN public_menu_card_url SET NOT NULL',
                'mysql' => 'ALTER TABLE companies MODIFY public_menu_card_url VARCHAR(255) NOT NULL',
                'mariadb' => 'ALTER TABLE companies MODIFY public_menu_card_url VARCHAR(255) NOT NULL',
                'sqlsrv' => 'ALTER TABLE companies ALTER COLUMN public_menu_card_url NVARCHAR(255) NOT NULL',
            ];

            if (array_key_exists($driver, $statements)) {
                DB::statement($statements[$driver]);
            }
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
