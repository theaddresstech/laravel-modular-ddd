<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Authorization;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;

class ModuleAuthorizationManager
{
    private array $permissions = [];
    private array $roles = [];
    private array $policies = [];

    public function __construct(
        private ModuleManagerInterface $moduleManager,
    ) {
        $this->loadCachedPermissions();
    }

    public function registerModulePermissions(string $moduleId): void
    {
        $module = $this->moduleManager->get($moduleId);

        if (!$module) {
            return;
        }

        $permissionsFile = $module->path . '/Config/permissions.php';

        if (file_exists($permissionsFile)) {
            $modulePermissions = require $permissionsFile;
            $this->registerPermissions($moduleId, $modulePermissions);
        }

        $this->discoverModulePolicies($moduleId, $module->path);
        $this->cachePermissions();
    }

    public function registerPermissions(string $moduleId, array $permissions): void
    {
        foreach ($permissions as $permission => $config) {
            $this->permissions["{$moduleId}.{$permission}"] = [
                'module' => $moduleId,
                'permission' => $permission,
                'description' => $config['description'] ?? '',
                'group' => $config['group'] ?? 'general',
                'dependencies' => $config['dependencies'] ?? [],
                'metadata' => $config['metadata'] ?? [],
            ];

            // Register with Laravel's Gate
            Gate::define("{$moduleId}.{$permission}", fn ($user, ...$args) => $this->checkPermission($user, $moduleId, $permission, $args, $config));
        }
    }

    public function registerRole(string $moduleId, string $role, array $permissions): void
    {
        $roleKey = "{$moduleId}.{$role}";
        $this->roles[$roleKey] = [
            'module' => $moduleId,
            'role' => $role,
            'permissions' => array_map(static fn ($perm) => "{$moduleId}.{$perm}", $permissions),
        ];

        $this->cachePermissions();
    }

    public function checkModuleAccess(mixed $user, string $moduleId): bool
    {
        // Check if user has any permission in the module
        $modulePermissions = array_filter(
            $this->permissions,
            static fn ($perm) => $perm['module'] === $moduleId,
        );

        foreach ($modulePermissions as $permissionKey => $permission) {
            if (Gate::forUser($user)->allows($permissionKey)) {
                return true;
            }
        }

        return false;
    }

    public function getUserModulePermissions(mixed $user, string $moduleId): array
    {
        $userPermissions = [];
        $modulePermissions = array_filter(
            $this->permissions,
            static fn ($perm) => $perm['module'] === $moduleId,
        );

        foreach ($modulePermissions as $permissionKey => $permission) {
            if (Gate::forUser($user)->allows($permissionKey)) {
                $userPermissions[] = $permission['permission'];
            }
        }

        return $userPermissions;
    }

    public function getModulePermissions(string $moduleId): array
    {
        return array_filter(
            $this->permissions,
            static fn ($perm) => $perm['module'] === $moduleId,
        );
    }

    public function getAllPermissions(): array
    {
        return $this->permissions;
    }

    public function hasPermission(mixed $user, string $moduleId, string $permission): bool
    {
        $permissionKey = "{$moduleId}.{$permission}";

        return Gate::forUser($user)->allows($permissionKey);
    }

    public function hasRole(mixed $user, string $moduleId, string $role): bool
    {
        $roleKey = "{$moduleId}.{$role}";

        if (!isset($this->roles[$roleKey])) {
            return false;
        }

        $rolePermissions = $this->roles[$roleKey]['permissions'];

        foreach ($rolePermissions as $permission) {
            if (!Gate::forUser($user)->allows($permission)) {
                return false;
            }
        }

        return true;
    }

    public function generatePermissionMatrix(): array
    {
        $matrix = [];

        foreach ($this->permissions as $permissionKey => $permission) {
            $moduleId = $permission['module'];

            if (!isset($matrix[$moduleId])) {
                $matrix[$moduleId] = [
                    'module_name' => $moduleId,
                    'permissions' => [],
                    'groups' => [],
                ];
            }

            $group = $permission['group'];
            if (!isset($matrix[$moduleId]['groups'][$group])) {
                $matrix[$moduleId]['groups'][$group] = [];
            }

            $matrix[$moduleId]['permissions'][] = $permission;
            $matrix[$moduleId]['groups'][$group][] = $permission;
        }

        return $matrix;
    }

    public function createPermissionMiddleware(string $permission): string
    {
        return "module.permission:{$permission}";
    }

    public function createRoleMiddleware(string $role): string
    {
        return "module.role:{$role}";
    }

    public function validatePermissionDependencies(string $permissionKey): array
    {
        if (!isset($this->permissions[$permissionKey])) {
            return ['valid' => false, 'missing' => [$permissionKey]];
        }

        $permission = $this->permissions[$permissionKey];
        $dependencies = $permission['dependencies'] ?? [];
        $missing = [];

        foreach ($dependencies as $dependency) {
            if (!isset($this->permissions[$dependency])) {
                $missing[] = $dependency;
            }
        }

        return [
            'valid' => empty($missing),
            'missing' => $missing,
        ];
    }

    public function syncModulePermissions(): void
    {
        $modules = $this->moduleManager->getActiveModules();

        foreach ($modules as $module) {
            $this->registerModulePermissions($module['name']);
        }

        Log::info('Module permissions synchronized', [
            'modules_count' => count($modules),
            'permissions_count' => count($this->permissions),
        ]);
    }

    private function checkPermission(mixed $user, string $moduleId, string $permission, array $args, array $config): bool
    {
        // Check if user has the specific permission
        if (method_exists($user, 'hasModulePermission')) {
            return $user->hasModulePermission($moduleId, $permission);
        }

        // Check if user has a role that includes this permission
        if (method_exists($user, 'hasModuleRole')) {
            $roleKey = "{$moduleId}.admin";
            if (isset($this->roles[$roleKey]) && $user->hasModuleRole($moduleId, 'admin')) {
                return true;
            }
        }

        // Custom authorization logic based on config
        if (isset($config['callback']) && is_callable($config['callback'])) {
            return $config['callback']($user, ...$args);
        }

        // Default to checking Laravel's standard permission system
        if (method_exists($user, 'can')) {
            return $user->can("{$moduleId}.{$permission}");
        }

        return false;
    }

    private function discoverModulePolicies(string $moduleId, string $modulePath): void
    {
        $policiesPath = $modulePath . '/Policies';

        if (!is_dir($policiesPath)) {
            return;
        }

        $policyFiles = glob($policiesPath . '/*.php');

        foreach ($policyFiles as $policyFile) {
            $className = basename($policyFile, '.php');
            $policyClass = "Modules\\{$moduleId}\\Policies\\{$className}";

            if (class_exists($policyClass)) {
                // Extract model name from policy name (e.g., UserPolicy -> User)
                $modelName = str_replace('Policy', '', $className);
                $modelClass = "Modules\\{$moduleId}\\Domain\\Entities\\{$modelName}";

                if (class_exists($modelClass)) {
                    Gate::policy($modelClass, $policyClass);

                    $this->policies["{$moduleId}.{$modelName}"] = [
                        'module' => $moduleId,
                        'model' => $modelClass,
                        'policy' => $policyClass,
                    ];
                }
            }
        }
    }

    private function loadCachedPermissions(): void
    {
        $cached = Cache::get('module_permissions', []);

        if (isset($cached['permissions'])) {
            $this->permissions = $cached['permissions'];
        }

        if (isset($cached['roles'])) {
            $this->roles = $cached['roles'];
        }

        if (isset($cached['policies'])) {
            $this->policies = $cached['policies'];
        }
    }

    private function cachePermissions(): void
    {
        Cache::put('module_permissions', [
            'permissions' => $this->permissions,
            'roles' => $this->roles,
            'policies' => $this->policies,
        ], 3600); // Cache for 1 hour
    }
}
