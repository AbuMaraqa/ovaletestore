<?php

namespace App\Console\Commands;

use App\Support\Permissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

/**
 * Part of every deploy (after `migrate`): creates permissions added to App\Support\Permissions,
 * grants them to the built-in roles, removes retired ones. Roles edited in the admin keep their edits.
 */
#[Signature('permissions:sync')]
#[Description('Sync permissions and built-in roles with App\Support\Permissions (idempotent; run on every deploy)')]
class SyncPermissions extends Command
{
    public function handle(): int
    {
        $before = Permission::query()->pluck('name')->all();
        (new RolesAndPermissionsSeeder)->run();

        $added = array_diff(Permissions::all(), $before);
        $removed = array_diff($before, Permissions::all());
        $this->components->info(sprintf('Permissions: %d total, %d added, %d removed.', count(Permissions::all()), count($added), count($removed)));
        foreach ($removed as $name) {
            $this->components->warn("Removed: {$name} (roles that had it no longer do)");
        }

        return self::SUCCESS;
    }
}
