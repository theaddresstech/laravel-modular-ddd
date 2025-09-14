<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Authorization\Traits;

use TaiCrm\LaravelModularDdd\Authorization\ModuleAuthorizationManager;

trait HasModulePermissions
{
    /**
     * Get all module permissions for this user.
     */
    public function getModulePermissions(): array
    {
        if (!$this->relationLoaded('modulePermissions')) {
            $this->load('modulePermissions');
        }

        return $this->modulePermissions->pluck('permission')->toArray();
    }

    /**
     * Check if user has a specific module permission.
     */
    public function hasModulePermission(string $moduleId, string $permission): bool
    {
        $permissionKey = "{$moduleId}.{$permission}";
        return $this->getModulePermissions()->contains($permissionKey);
    }

    /**
     * Check if user has any permission in a module.
     */
    public function hasModuleAccess(string $moduleId): bool
    {
        $authManager = app(ModuleAuthorizationManager::class);
        return $authManager->checkModuleAccess($this, $moduleId);
    }

    /**
     * Get user's permissions for a specific module.
     */
    public function getModulePermissionsFor(string $moduleId): array
    {
        $authManager = app(ModuleAuthorizationManager::class);
        return $authManager->getUserModulePermissions($this, $moduleId);
    }

    /**
     * Grant a module permission to the user.
     */
    public function grantModulePermission(string $moduleId, string $permission): void
    {
        $permissionKey = "{$moduleId}.{$permission}";

        if (!$this->hasModulePermission($moduleId, $permission)) {
            $this->modulePermissions()->create([
                'permission' => $permissionKey,
                'module_id' => $moduleId,
                'granted_at' => now(),
            ]);

            // Clear cached permissions
            $this->unsetRelation('modulePermissions');
        }
    }

    /**
     * Revoke a module permission from the user.
     */
    public function revokeModulePermission(string $moduleId, string $permission): void
    {
        $permissionKey = "{$moduleId}.{$permission}";

        $this->modulePermissions()
            ->where('permission', $permissionKey)
            ->delete();

        // Clear cached permissions
        $this->unsetRelation('modulePermissions');
    }

    /**
     * Grant multiple module permissions.
     */
    public function grantModulePermissions(string $moduleId, array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->grantModulePermission($moduleId, $permission);
        }
    }

    /**
     * Revoke multiple module permissions.
     */
    public function revokeModulePermissions(string $moduleId, array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->revokeModulePermission($moduleId, $permission);
        }
    }

    /**
     * Sync module permissions (revoke all and grant specified).
     */
    public function syncModulePermissions(string $moduleId, array $permissions): void
    {
        // Remove all existing permissions for this module
        $this->modulePermissions()
            ->where('module_id', $moduleId)
            ->delete();

        // Grant new permissions
        $this->grantModulePermissions($moduleId, $permissions);
    }

    /**
     * Check if user has a module role.
     */
    public function hasModuleRole(string $moduleId, string $role): bool
    {
        $authManager = app(ModuleAuthorizationManager::class);
        return $authManager->hasRole($this, $moduleId, $role);
    }

    /**
     * Get all module roles for this user.
     */
    public function getModuleRoles(): array
    {
        if (!$this->relationLoaded('moduleRoles')) {
            $this->load('moduleRoles');
        }

        return $this->moduleRoles->pluck('role')->toArray();
    }

    /**
     * Grant a module role to the user.
     */
    public function grantModuleRole(string $moduleId, string $role): void
    {
        $roleKey = "{$moduleId}.{$role}";

        if (!$this->hasModuleRole($moduleId, $role)) {
            $this->moduleRoles()->create([
                'role' => $roleKey,
                'module_id' => $moduleId,
                'granted_at' => now(),
            ]);

            // Clear cached roles
            $this->unsetRelation('moduleRoles');
        }
    }

    /**
     * Revoke a module role from the user.
     */
    public function revokeModuleRole(string $moduleId, string $role): void
    {
        $roleKey = "{$moduleId}.{$role}";

        $this->moduleRoles()
            ->where('role', $roleKey)
            ->delete();

        // Clear cached roles
        $this->unsetRelation('moduleRoles');
    }

    /**
     * Define the relationship with module permissions.
     */
    public function modulePermissions()
    {
        return $this->hasMany(
            config('modular-ddd.models.user_module_permission', 'App\Models\UserModulePermission')
        );
    }

    /**
     * Define the relationship with module roles.
     */
    public function moduleRoles()
    {
        return $this->hasMany(
            config('modular-ddd.models.user_module_role', 'App\Models\UserModuleRole')
        );
    }

    /**
     * Get permission matrix for all modules.
     */
    public function getPermissionMatrix(): array
    {
        $authManager = app(ModuleAuthorizationManager::class);
        $allPermissions = $authManager->getAllPermissions();
        $userPermissions = $this->getModulePermissions();

        $matrix = [];

        foreach ($allPermissions as $permissionKey => $permission) {
            $moduleId = $permission['module'];

            if (!isset($matrix[$moduleId])) {
                $matrix[$moduleId] = [
                    'module' => $moduleId,
                    'has_access' => false,
                    'permissions' => [],
                ];
            }

            $hasPermission = in_array($permissionKey, $userPermissions);
            $matrix[$moduleId]['permissions'][$permission['permission']] = $hasPermission;

            if ($hasPermission) {
                $matrix[$moduleId]['has_access'] = true;
            }
        }

        return $matrix;
    }
}