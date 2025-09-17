<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Authorization\Traits;

use Spatie\Permission\Traits\HasRoles;
use TaiCrm\LaravelModularDdd\Authorization\ModulePermissionManager;

trait HasModulePermissions
{
    use HasRoles;

    /**
     * Check if user has a specific module permission.
     */
    public function hasModulePermission(string $moduleId, string $permission): bool
    {
        $manager = app(ModulePermissionManager::class);

        return $manager->userHasModulePermission($this, $moduleId, $permission);
    }

    /**
     * Grant a module permission to the user.
     */
    public function grantModulePermission(string $moduleId, string $permission): void
    {
        $manager = app(ModulePermissionManager::class);
        $manager->grantModulePermission($this, $moduleId, $permission);
    }

    /**
     * Grant multiple module permissions to the user.
     */
    public function grantModulePermissions(string $moduleId, array $permissions): void
    {
        $manager = app(ModulePermissionManager::class);
        foreach ($permissions as $permission) {
            $manager->grantModulePermission($this, $moduleId, $permission);
        }
    }

    /**
     * Revoke a module permission from the user.
     */
    public function revokeModulePermission(string $moduleId, string $permission): void
    {
        $manager = app(ModulePermissionManager::class);
        $manager->revokeModulePermission($this, $moduleId, $permission);
    }

    /**
     * Assign a module role to the user.
     */
    public function assignModuleRole(string $moduleId, string $role): void
    {
        $manager = app(ModulePermissionManager::class);
        $manager->assignModuleRole($this, $moduleId, $role);
    }

    /**
     * Remove a module role from the user.
     */
    public function removeModuleRole(string $moduleId, string $role): void
    {
        $manager = app(ModulePermissionManager::class);
        $manager->removeModuleRole($this, $moduleId, $role);
    }

    /**
     * Check if user has any permission in a module.
     */
    public function hasModuleAccess(string $moduleId): bool
    {
        return $this->getAllPermissions()
            ->contains(static fn ($permission) => str_starts_with($permission->name, "{$moduleId}."));
    }

    /**
     * Get user's permissions for a specific module.
     */
    public function getModulePermissionsFor(string $moduleId): array
    {
        $manager = app(ModulePermissionManager::class);

        return $manager->getUserModulePermissions($this, $moduleId);
    }
}
