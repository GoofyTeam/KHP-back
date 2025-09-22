<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('show_menu_images');
            }

            if (! Schema::hasColumn('companies', 'contact_name')) {
                $table->string('contact_name')->nullable()->after('logo_path');
            }

            if (! Schema::hasColumn('companies', 'contact_email')) {
                $table->string('contact_email')->nullable()->after('contact_name');
            }

            if (! Schema::hasColumn('companies', 'contact_phone')) {
                $table->string('contact_phone', 64)->nullable()->after('contact_email');
            }

            if (! Schema::hasColumn('companies', 'address_line')) {
                $table->string('address_line')->nullable()->after('contact_phone');
            }

            if (! Schema::hasColumn('companies', 'postal_code')) {
                $table->string('postal_code', 32)->nullable()->after('address_line');
            }

            if (! Schema::hasColumn('companies', 'city')) {
                $table->string('city')->nullable()->after('postal_code');
            }

            if (! Schema::hasColumn('companies', 'country')) {
                $table->string('country')->nullable()->after('city');
            }
        });
    }

    public function down(): void
    {
        $columns = [
            'logo_path',
            'contact_name',
            'contact_email',
            'contact_phone',
            'address_line',
            'postal_code',
            'city',
            'country',
        ];

        $existing = array_values(array_filter($columns, fn ($column) => Schema::hasColumn('companies', $column)));

        if ($existing !== []) {
            Schema::table('companies', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }
};
