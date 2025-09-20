<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('menu_type_public_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_type_id')->constrained('menu_types')->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'menu_type_id']);
        });

        Schema::table('menus', function (Blueprint $table) {
            $table->foreignId('menu_type_id')->nullable()->after('company_id')->constrained('menu_types')->restrictOnDelete();
            $table->unsignedInteger('public_priority')->default(0)->after('is_a_la_carte');
        });

        $now = now();

        $typeOrderColumn = Schema::hasColumn('menus', 'type_index')
            ? DB::raw('MIN(type_index) as type_order')
            : DB::raw('0 as type_order');

        $existingTypes = DB::table('menus')
            ->select('company_id', 'type', $typeOrderColumn)
            ->groupBy('company_id', 'type')
            ->orderBy('company_id')
            ->orderBy('type')
            ->get();

        $typeIdMap = [];

        foreach ($existingTypes as $typeRow) {
            if (! $typeRow->type) {
                continue;
            }

            $typeId = DB::table('menu_types')->insertGetId([
                'company_id' => $typeRow->company_id,
                'name' => $typeRow->type,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('menu_type_public_orders')->insert([
                'menu_type_id' => $typeId,
                'company_id' => $typeRow->company_id,
                'position' => $typeRow->type_order ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $typeIdMap[$typeRow->company_id.'|'.$typeRow->type] = $typeId;
        }

        $menuSelect = ['id', 'company_id', 'type'];

        if (Schema::hasColumn('menus', 'menu_index')) {
            $menuSelect[] = 'menu_index';
        } else {
            $menuSelect[] = DB::raw('0 as menu_index');
        }

        $menus = DB::table('menus')->select($menuSelect)->get();

        foreach ($menus as $menu) {
            if ($menu->type) {
                $key = $menu->company_id.'|'.$menu->type;
                $menuTypeId = $typeIdMap[$key] ?? null;

                if ($menuTypeId) {
                    DB::table('menus')
                        ->where('id', $menu->id)
                        ->update([
                            'menu_type_id' => $menuTypeId,
                            'public_priority' => $menu->menu_index ?? 0,
                        ]);
                }
            } else {
                DB::table('menus')
                    ->where('id', $menu->id)
                    ->update([
                        'public_priority' => $menu->menu_index ?? 0,
                    ]);
            }
        }

        Schema::table('menus', function (Blueprint $table) {
            if (Schema::hasColumn('menus', 'type_index')) {
                $table->dropColumn('type_index');
            }
            if (Schema::hasColumn('menus', 'menu_index')) {
                $table->dropColumn('menu_index');
            }
            if (Schema::hasColumn('menus', 'type')) {
                $table->dropColumn('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->string('type')->default('')->after('is_returnable');
            $table->unsignedInteger('type_index')->default(0)->after('type');
            $table->unsignedInteger('menu_index')->default(0)->after('type_index');
        });

        $menus = DB::table('menus as m')
            ->leftJoin('menu_types as mt', 'm.menu_type_id', '=', 'mt.id')
            ->leftJoin('menu_type_public_orders as mpo', 'mt.id', '=', 'mpo.menu_type_id')
            ->select('m.id', 'mt.name as type_name', 'mpo.position as type_index', 'm.public_priority')
            ->get();

        foreach ($menus as $menu) {
            DB::table('menus')
                ->where('id', $menu->id)
                ->update([
                    'type' => $menu->type_name ?? '',
                    'type_index' => $menu->type_index ?? 0,
                    'menu_index' => $menu->public_priority ?? 0,
                ]);
        }

        Schema::table('menus', function (Blueprint $table) {
            if (Schema::hasColumn('menus', 'menu_type_id')) {
                $table->dropForeign(['menu_type_id']);
                $table->dropColumn(['menu_type_id']);
            }
            if (Schema::hasColumn('menus', 'public_priority')) {
                $table->dropColumn('public_priority');
            }
        });

        Schema::dropIfExists('menu_type_public_orders');
        Schema::dropIfExists('menu_types');
    }
};
