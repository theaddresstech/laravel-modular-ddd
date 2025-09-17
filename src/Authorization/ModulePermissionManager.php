<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Authorization;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;

class ModulePermissionManager
{
    private const CACHE_KEY_PREFIX = 'modular_ddd_permissions';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private ModuleManagerInterface $moduleManager,
    ) {}

    /**
     * Register permissions from a module's configuration file.
     */
    public function registerModulePermissions(string $moduleId): void
    {
        $module = $this->moduleManager->get($moduleId);

        if (!$module) {
            Log::warning("Module not found when registering permissions: {$moduleId}");

            return;
        }

        $permissionsFile = $module->path . '/Config/permissions.php';

        if (file_exists($permissionsFile)) {
            $modulePermissions = require $permissionsFile;
            $this->syncPermissions($moduleId, $modulePermissions);
        }

        $this->clearCache();
    }

    /**
     * Sync module permissions with Spatie Permission system.
     */
    public function syncPermissions(string $moduleId, array $permissions): void
    {
        foreach ($permissions as $permission => $config) {
            $permissionName = $this->buildPermissionName($moduleId, $permission);

            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ], [
                'description' => $config['description'] ?? '',
                'module_id' => $moduleId,
                'group' => $config['group'] ?? 'general',
            ]);
        }

        Log::info("Synced permissions for module: {$moduleId}", [
            'permissions_count' => count($permissions),
        ]);
    }

    /**
     * Create a module-specific role.
     */
    public function createModuleRole(string $moduleId, string $roleName, array $permissions = []): Role
    {
        $fullRoleName = $this->buildRoleName($moduleId, $roleName);

        $role = Role::firstOrCreate([
            'name' => $fullRoleName,
            'guard_name' => 'web',
        ], [
            'module_id' => $moduleId,
        ]);

        if (!empty($permissions)) {
            $modulePermissions = collect($permissions)
                ->map(fn ($permission) => $this->buildPermissionName($moduleId, $permission))
                ->toArray();

            $role->syncPermissions($modulePermissions);
        }

        $this->clearCache();

        return $role;
    }

    /**
     * Get all permissions for a specific module.
     */
    public function getModulePermissions(string $moduleId): Collection
    {
        $cacheKey = $this->getCacheKey("permissions_{$moduleId}");

        return Cache::remember($cacheKey, self::CACHE_TTL, static fn () => Permission::where('name', 'like', "{$moduleId}.%")->get());
    }

    /**
     * Get all roles for a specific module.
     */
    public function getModuleRoles(string $moduleId): Collection
    {
        $cacheKey = $this->getCacheKey("roles_{$moduleId}");

        return Cache::remember($cacheKey, self::CACHE_TTL, static fn () => Role::where('name', 'like', "{$moduleId}.%")->get());
    }

    /**
     * Check if a user has permission for a module action.
     *
     * @param mixed $user
     */
    public function userHasModulePermission(mixed $user, string $moduleId, string $permission): bool
    {
        $permissionName = $this->buildPermissionName($moduleId, $permission);

        return $user->hasPermissionTo($permissionName);
    }

    /**
     * Grant module permission to user.
     *
     * @param mixed $user
     */
    public function grantModulePermission(mixed $user, string $moduleId, string $permission): void
    {
        $permissionName = $this->buildPermissionName($moduleId, $permission);

        // Ensure permission exists
        Permission::firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ], [
            'module_id' => $moduleId,
        ]);

        $user->givePermissionTo($permissionName);
        $this->clearCache();
    }

    /**
     * Revoke module permission from user.
     *
     * @param mixed $user
     */
    public function revokeModulePermission(mixed $user, string $moduleId, string $permission): void
    {
        $permissionName = $this->buildPermissionName($moduleId, $permission);
        $user->revokePermissionTo($permissionName);
        $this->clearCache();
    }

    /**
     * Assign module role to user.
     *
     * @param mixed $user
     */
    public function assignModuleRole(mixed $user, string $moduleId, string $role): void
    {
        $roleName = $this->buildRoleName($moduleId, $role);
        $user->assignRole($roleName);
        $this->clearCache();
    }

    /**
     * Remove module role from user.
     *
     * @param mixed $user
     */
    public function removeModuleRole(mixed $user, string $moduleId, string $role): void
    {
        $roleName = $this->buildRoleName($moduleId, $role);
        $user->removeRole($roleName);
        $this->clearCache();
    }

    /**
     * Get user's permissions for a specific module.
     *
     * @param mixed $user
     */
    public function getUserModulePermissions(mixed $user, string $moduleId): array
    {
        return $user->getAllPermissions()
            ->filter(static fn ($permission) => str_starts_with($permission->name, "{$moduleId}."))
            ->pluck('name')
            ->map(static fn ($name) => str_replace("{$moduleId}.", '', $name))
            ->toArray();
    }

    /**
     * Remove all permissions and roles for a module.
     */
    public function removeModulePermissions(string $moduleId): void
    {
        // Remove permissions
        Permission::where('name', 'like', "{$moduleId}.%")->delete();

        // Remove roles
        Role::where('name', 'like', "{$moduleId}.%")->delete();

        $this->clearCache();

        Log::info("Removed all permissions and roles for module: {$moduleId}");
    }

    /**
     * Build full permission name with module prefix.
     */
    private function buildPermissionName(string $moduleId, string $permission): string
    {
        return "{$moduleId}.{$permission}";
    }

    /**
     * Build full role name with module prefix.
     */
    private function buildRoleName(string $moduleId, string $role): string
    {
        return "{$moduleId}.{$role}";
    }

    /**
     * Get cache key with prefix.
     */
    private function getCacheKey(string $key): string
    {
        return self::CACHE_KEY_PREFIX . "_{$key}";
    }

    /**
     * Clear all module permission cache.
     */
    private function clearCache(): void
    {
        Cache::tags([self::CACHE_KEY_PREFIX])->flush();
    }
}
