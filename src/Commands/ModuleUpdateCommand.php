<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\Exceptions\ModuleNotFoundException;
use TaiCrm\LaravelModularDdd\Exceptions\ModuleInstallationException;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class ModuleUpdateCommand extends Command
{
    protected $signature = 'module:update
                            {module? : The module to update}
                            {--all : Update all modules}
                            {--version= : Specific version to update to}
                            {--force : Force update without confirmation}
                            {--backup : Create backup before updating}';

    protected $description = 'Update a module to a newer version';

    public function __construct(
        private ModuleManagerInterface $moduleManager,
        private Filesystem $files
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->updateAllModules();
        }

        $moduleName = $this->argument('module');
        if (!$moduleName) {
            $this->error('Please specify a module name or use --all flag');
            return self::FAILURE;
        }

        return $this->updateModule($moduleName);
    }

    private function updateAllModules(): int
    {
        $modules = $this->moduleManager->list()
            ->filter(fn($module) => $module->isInstalled());

        if ($modules->isEmpty()) {
            $this->info('No installed modules found.');
            return self::SUCCESS;
        }

        $this->info('🔄 Checking for updates for all installed modules...');
        $success = true;

        foreach ($modules as $module) {
            $this->newLine();
            $this->line("📦 Checking module: {$module->name}");

            $result = $this->performModuleUpdate($module->name);
            if ($result !== self::SUCCESS) {
                $success = false;
            }
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }

    private function updateModule(string $moduleName): int
    {
        try {
            $module = $this->moduleManager->getInfo($moduleName);

            if (!$module) {
                throw new ModuleNotFoundException($moduleName);
            }

            if (!$module->isInstalled()) {
                $this->error("❌ Module '{$moduleName}' is not installed.");
                return self::FAILURE;
            }

            $this->info("🔄 Updating module: {$moduleName}");

            return $this->performModuleUpdate($moduleName);

        } catch (ModuleNotFoundException $e) {
            $this->error("❌ " . $e->getMessage());
            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error("❌ Update failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function performModuleUpdate(string $moduleName): int
    {
        try {
            $currentModule = $this->moduleManager->getInfo($moduleName);
            $currentVersion = $currentModule->version;

            // Check for available updates
            $updateInfo = $this->checkForUpdates($moduleName, $currentVersion);

            if (!$updateInfo['hasUpdate']) {
                $this->line("   ✅ Module '{$moduleName}' is already up to date (v{$currentVersion})");
                return self::SUCCESS;
            }

            $newVersion = $this->option('version') ?? $updateInfo['latestVersion'];

            $this->displayUpdateInfo($moduleName, $currentVersion, $newVersion, $updateInfo);

            if (!$this->option('force') && !$this->confirmUpdate($moduleName, $currentVersion, $newVersion)) {
                $this->info('   Update cancelled.');
                return self::SUCCESS;
            }

            // Create backup if requested
            if ($this->option('backup')) {
                $this->createBackup($currentModule);
            }

            // Perform the update
            $this->performUpdate($moduleName, $newVersion, $updateInfo);

            $this->info("   ✅ Module '{$moduleName}' updated successfully to v{$newVersion}");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("   ❌ Failed to update module '{$moduleName}': " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function checkForUpdates(string $moduleName, string $currentVersion): array
    {
        // This is a placeholder implementation
        // In a real implementation, this would:
        // 1. Check a package registry or repository
        // 2. Compare versions using semantic versioning
        // 3. Return available updates

        // For demo purposes, simulate update checking
        $hasUpdate = rand(1, 3) === 1; // 1/3 chance of having an update
        $latestVersion = $hasUpdate ? $this->incrementVersion($currentVersion) : $currentVersion;

        return [
            'hasUpdate' => $hasUpdate,
            'currentVersion' => $currentVersion,
            'latestVersion' => $latestVersion,
            'changelog' => $hasUpdate ? $this->generateMockChangelog($currentVersion, $latestVersion) : [],
            'breaking' => false,
            'security' => rand(1, 10) === 1, // 1/10 chance of security update
        ];
    }

    private function incrementVersion(string $version): string
    {
        $parts = explode('.', $version);
        if (count($parts) >= 3) {
            $parts[2] = (int)$parts[2] + 1;
            return implode('.', $parts);
        }
        return $version;
    }

    private function generateMockChangelog(string $from, string $to): array
    {
        return [
            'features' => ['New feature added', 'Improved performance'],
            'fixes' => ['Fixed bug in module loading', 'Resolved dependency issues'],
            'breaking' => [],
        ];
    }

    private function displayUpdateInfo(string $moduleName, string $current, string $new, array $info): void
    {
        $this->newLine();
        $this->line("   📋 <comment>Update Available:</comment>");
        $this->line("   Current Version: {$current}");
        $this->line("   New Version: {$new}");

        if ($info['security']) {
            $this->line("   <error>🔒 Security Update Available</error>");
        }

        if (!empty($info['changelog'])) {
            $this->line("   <comment>Changes:</comment>");

            if (!empty($info['changelog']['features'])) {
                $this->line("   <info>✨ New Features:</info>");
                foreach ($info['changelog']['features'] as $feature) {
                    $this->line("     • {$feature}");
                }
            }

            if (!empty($info['changelog']['fixes'])) {
                $this->line("   <info>🐛 Bug Fixes:</info>");
                foreach ($info['changelog']['fixes'] as $fix) {
                    $this->line("     • {$fix}");
                }
            }

            if (!empty($info['changelog']['breaking'])) {
                $this->line("   <error>💥 Breaking Changes:</error>");
                foreach ($info['changelog']['breaking'] as $breaking) {
                    $this->line("     • {$breaking}");
                }
            }
        }
    }

    private function confirmUpdate(string $moduleName, string $current, string $new): bool
    {
        return $this->confirm(
            "Do you want to update '{$moduleName}' from v{$current} to v{$new}?",
            true
        );
    }

    private function createBackup($module): void
    {
        $backupPath = storage_path('app/module-backups/' . $module->name);
        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupDir = "{$backupPath}/{$timestamp}";

        if (!$this->files->exists($backupDir)) {
            $this->files->makeDirectory($backupDir, 0755, true);
        }

        // Copy module files to backup directory
        $this->files->copyDirectory($module->path, $backupDir);

        $this->line("   💾 Backup created at: {$backupDir}");
    }

    private function performUpdate(string $moduleName, string $newVersion, array $updateInfo): void
    {
        // This is a placeholder implementation
        // In a real implementation, this would:
        // 1. Download the new module version
        // 2. Disable the old module
        // 3. Replace module files
        // 4. Run update migrations
        // 5. Re-enable the module
        // 6. Clear caches

        $this->line("   🔄 Downloading update...");
        sleep(1); // Simulate download time

        $this->line("   🔄 Installing update...");
        sleep(1); // Simulate installation time

        $this->line("   🔄 Running migrations...");
        $this->call('module:migrate', [
            'module' => $moduleName,
            '--force' => true,
        ]);

        $this->line("   🔄 Clearing caches...");
        $this->call('module:cache', ['action' => 'clear']);

        // Update module version in manifest (simulation)
        $module = $this->moduleManager->getInfo($moduleName);
        if ($module) {
            $manifestPath = $module->path . '/manifest.json';
            if ($this->files->exists($manifestPath)) {
                $manifest = json_decode($this->files->get($manifestPath), true);
                $manifest['version'] = $newVersion;
                $this->files->put(
                    $manifestPath,
                    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );
            }
        }
    }
}