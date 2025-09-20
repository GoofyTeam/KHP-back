<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $defaultTypes = [
            ['name' => 'Entrées', 'position' => 0],
            ['name' => 'Plats', 'position' => 1],
            ['name' => 'Desserts', 'position' => 2],
            ['name' => 'Accompagnements', 'position' => 3],
        ];

        $now = now();

        $companies = DB::table('companies')->select('id')->get();

        foreach ($companies as $company) {
            foreach ($defaultTypes as $type) {
                $existingType = DB::table('menu_types')
                    ->where('company_id', $company->id)
                    ->whereRaw('LOWER(name) = ?', [Str::lower($type['name'])])
                    ->first();

                if ($existingType) {
                    $menuTypeId = $existingType->id;
                } else {
                    $menuTypeId = DB::table('menu_types')->insertGetId([
                        'company_id' => $company->id,
                        'name' => $type['name'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $existingOrder = DB::table('menu_type_public_orders')
                    ->where('company_id', $company->id)
                    ->where('menu_type_id', $menuTypeId)
                    ->first();

                if (! $existingOrder) {
                    DB::table('menu_type_public_orders')->insert([
                        'menu_type_id' => $menuTypeId,
                        'company_id' => $company->id,
                        'position' => $type['position'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $defaultTypeId = DB::table('menu_types')
                ->where('company_id', $company->id)
                ->whereRaw('LOWER(name) = ?', [Str::lower('Plats')])
                ->value('id');

            if ($defaultTypeId) {
                DB::table('menus')
                    ->where('company_id', $company->id)
                    ->whereNull('menu_type_id')
                    ->update([
                        'menu_type_id' => $defaultTypeId,
                    ]);
            }
        }

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE menus MODIFY menu_type_id BIGINT UNSIGNED NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE menus MODIFY menu_type_id BIGINT UNSIGNED NULL');
        }
    }
};
