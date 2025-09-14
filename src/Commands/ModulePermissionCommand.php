<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use TaiCrm\LaravelModularDdd\Authorization\ModuleAuthorizationManager;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use Illuminate\Console\Command;

class ModulePermissionCommand extends Command
{
    protected $signature = 'module:permission
                            {action : Action to perform (list|grant|revoke|sync|matrix)}
                            {--module= : Module name}
                            {--user= : User ID or email}
                            {--permission= : Permission name}
                            {--role= : Role name}
                            {--export= : Export to file}';

    protected $description = 'Manage module permissions and roles';

    public function __construct(
        private ModuleAuthorizationManager $authManager,
        private ModuleManagerInterface $moduleManager
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listPermissions(),
            'grant' => $this->grantPermission(),
            'revoke' => $this->revokePermission(),
            'sync' => $this->syncPermissions(),
            'matrix' => $this->showPermissionMatrix(),
            default => $this->invalidAction($action),
        };
    }

    private function listPermissions(): int
    {
        $moduleId = $this->option('module');

        if ($moduleId) {
            $this->listModulePermissions($moduleId);
        } else {
            $this->listAllPermissions();
        }

        return 0;
    }

    private function listModulePermissions(string $moduleId): void
    {
        $permissions = $this->authManager->getModulePermissions($moduleId);

        if (empty($permissions)) {
            $this->warn("No permissions found for module: {$moduleId}");
            return;
        }

        $this->info("📋 Permissions for module: {$moduleId}");
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $tableData = [];
        foreach ($permissions as $permissionKey => $permission) {
            $tableData[] = [
                $permission['permission'],
                $permission['group'],
                $permission['description'],
                empty($permission['dependencies']) ? 'None' : implode(', ', $permission['dependencies']),
            ];
        }

        $this->table(
            ['Permission', 'Group', 'Description', 'Dependencies'],
            $tableData
        );
    }

    private function listAllPermissions(): void
    {
        $allPermissions = $this->authManager->getAllPermissions();
        $modules = array_unique(array_column($allPermissions, 'module'));

        $this->info('📋 All Module Permissions');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        foreach ($modules as $moduleId) {
            $modulePermissions = array_filter($allPermissions, fn($p) => $p['module'] === $moduleId);

            $this->newLine();
            $this->line("🏷️  <fg=blue>{$moduleId}</> (" . count($modulePermissions) . " permissions)");

            foreach ($modulePermissions as $permission) {
                $this->line("   • {$permission['permission']} - {$permission['description']}");
            }
        }

        $this->newLine();
        $this->info("Total: " . count($allPermissions) . " permissions across " . count($modules) . " modules");
    }

    private function grantPermission(): int
    {
        $user = $this->getUser();
        $module = $this->option('module');
        $permission = $this->option('permission');

        if (!$user || !$module || !$permission) {
            $this->error('User, module, and permission are required for grant action.');
            return 1;
        }

        try {
            $user->grantModulePermission($module, $permission);
            $this->info("✅ Granted permission '{$module}.{$permission}' to user: {$user->email}");
        } catch (\Exception $e) {
            $this->error("❌ Failed to grant permission: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }

    private function revokePermission(): int
    {
        $user = $this->getUser();
        $module = $this->option('module');
        $permission = $this->option('permission');

        if (!$user || !$module || !$permission) {
            $this->error('User, module, and permission are required for revoke action.');
            return 1;
        }

        try {
            $user->revokeModulePermission($module, $permission);
            $this->info("✅ Revoked permission '{$module}.{$permission}' from user: {$user->email}");
        } catch (\Exception $e) {
            $this->error("❌ Failed to revoke permission: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }

    private function syncPermissions(): int
    {
        $this->info('🔄 Synchronizing module permissions...');

        try {
            $this->authManager->syncModulePermissions();
            $this->info('✅ Module permissions synchronized successfully.');
        } catch (\Exception $e) {
            $this->error("❌ Failed to sync permissions: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }

    private function showPermissionMatrix(): int
    {
        $user = $this->getUser();
        $export = $this->option('export');

        if ($user) {
            $this->showUserPermissionMatrix($user, $export);
        } else {
            $this->showSystemPermissionMatrix($export);
        }

        return 0;
    }

    private function showUserPermissionMatrix($user, ?string $export): void
    {
        $matrix = $user->getPermissionMatrix();

        $this->info("🔐 Permission Matrix for: {$user->email}");
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        foreach ($matrix as $moduleId => $moduleData) {
            $accessIcon = $moduleData['has_access'] ? '✅' : '❌';
            $this->newLine();
            $this->line("{$accessIcon} <fg=blue>{$moduleId}</>");

            if ($moduleData['has_access']) {
                foreach ($moduleData['permissions'] as $permission => $hasPermission) {
                    $icon = $hasPermission ? '✓' : '✗';
                    $color = $hasPermission ? 'green' : 'red';
                    $this->line("   <fg={$color}>{$icon}</> {$permission}");
                }
            } else {
                $this->line('   <fg=red>No access to this module</fg=red>');
            }
        }

        if ($export) {
            $this->exportMatrix($matrix, $export);
        }
    }

    private function showSystemPermissionMatrix(?string $export): void
    {
        $matrix = $this->authManager->generatePermissionMatrix();

        $this->info('🔐 System Permission Matrix');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        foreach ($matrix as $moduleId => $moduleData) {
            $this->newLine();
            $this->line("🏷️  <fg=blue>{$moduleId}</> (" . count($moduleData['permissions']) . " permissions)");

            // Group permissions by category
            foreach ($moduleData['groups'] as $group => $permissions) {
                $this->line("   📁 {$group}:");
                foreach ($permissions as $permission) {
                    $this->line("      • {$permission['permission']} - {$permission['description']}");
                }
            }
        }

        if ($export) {
            $this->exportMatrix($matrix, $export);
        }
    }

    private function getUser()
    {
        $userInput = $this->option('user');

        if (!$userInput) {
            return null;
        }

        $userModel = config('auth.providers.users.model', 'App\Models\User');

        // Try to find by ID first, then by email
        if (is_numeric($userInput)) {
            return $userModel::find($userInput);
        }

        return $userModel::where('email', $userInput)->first();
    }

    private function exportMatrix(array $matrix, string $filename): void
    {
        $exportData = [
            'exported_at' => now()->toISOString(),
            'matrix' => $matrix,
        ];

        file_put_contents($filename, json_encode($exportData, JSON_PRETTY_PRINT));
        $this->info("📄 Permission matrix exported to: {$filename}");
    }

    private function invalidAction(string $action): int
    {
        $this->error("Invalid action: {$action}");
        $this->line('Available actions: list, grant, revoke, sync, matrix');
        return 1;
    }
}