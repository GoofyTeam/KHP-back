<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Vérifier si nous utilisons PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement('CREATE EXTENSION IF NOT EXISTS fuzzystrmatch');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Vérifier si nous utilisons PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP EXTENSION IF EXISTS pg_trgm');
            DB::statement('DROP EXTENSION IF EXISTS fuzzystrmatch');
        }
    }
};
