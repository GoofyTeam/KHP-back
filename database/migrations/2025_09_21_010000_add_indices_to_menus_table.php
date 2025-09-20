<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Cette migration est désormais gérée par la refonte des types de menus
        // (2025_09_20_171040_refactor_menu_types_for_public_menu_card).
    }

    public function down(): void
    {
        // Aucune action nécessaire lors du rollback.
    }
};
