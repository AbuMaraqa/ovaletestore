<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Permissions are data the code depends on: syncing them in a migration means a deploy can never
 * ship buttons whose permission does not exist yet. The seeder is idempotent and keeps role edits.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RolesAndPermissionsSeeder)->run();
    }

    public function down(): void
    {
        // Data sync only — nothing to undo.
    }
};
