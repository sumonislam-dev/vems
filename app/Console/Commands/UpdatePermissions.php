<?php

namespace App\Console\Commands;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Safe, add-only counterpart to setup:permissions for live databases: adds
 * permissions and roles that are new in RolePermissionSeeder and grants each
 * seeded role its newly listed permissions. It never deletes or revokes
 * anything, so custom roles and permissions edited in the Roles screen stay
 * as they are; leftovers are only reported.
 */
class UpdatePermissions extends Command
{
    protected $signature = 'permissions:update {--dry-run : Show what would change without saving}';

    protected $description = 'Add new permissions and roles from RolePermissionSeeder without deleting or revoking anything';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $listed = RolePermissionSeeder::permissions();
        $existing = Permission::where('guard_name', 'web')->pluck('name')->all();

        $newPermissions = array_values(array_diff($listed, $existing));
        foreach ($newPermissions as $name) {
            $this->line("  <info>+ permission</info> {$name}");
            if (! $dryRun) {
                Permission::create(['name' => $name, 'guard_name' => 'web']);
            }
        }

        $changes = count($newPermissions);
        foreach (RolePermissionSeeder::roles() as $roleName => $grants) {
            $grants = $grants === '*' ? $listed : $grants;
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if (! $role) {
                $this->line("  <info>+ role</info> {$roleName} (".count($grants).' permissions)');
                $changes++;
                if (! $dryRun) {
                    Role::create(['name' => $roleName, 'guard_name' => 'web'])->givePermissionTo($grants);
                }

                continue;
            }

            $missing = array_values(array_diff($grants, $role->permissions->pluck('name')->all()));
            if ($missing !== []) {
                $this->line("  <info>~ role</info> {$roleName}: + ".implode(', ', $missing));
                $changes += count($missing);
                if (! $dryRun) {
                    $role->givePermissionTo($missing);
                }
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->reportLeftovers($existing, $listed);

        if ($changes === 0) {
            $this->info('Permissions are up to date.');
        } else {
            $this->info($dryRun ? "{$changes} change(s) would be made (dry run, nothing saved)." : "{$changes} change(s) applied.");
        }

        return self::SUCCESS;
    }

    /**
     * Point out what setup:permissions would delete, without touching it.
     *
     * @param  list<string>  $existing
     * @param  list<string>  $listed
     */
    private function reportLeftovers(array $existing, array $listed): void
    {
        $stalePermissions = array_values(array_diff($existing, $listed));
        $customRoles = Role::where('guard_name', 'web')
            ->whereNotIn('name', array_keys(RolePermissionSeeder::roles()))
            ->pluck('name')
            ->all();

        if ($stalePermissions !== []) {
            $this->warn('  Not in the seeder (kept): '.implode(', ', $stalePermissions));
        }
        if ($customRoles !== []) {
            $this->warn('  Custom roles (kept): '.implode(', ', $customRoles));
        }
    }
}
