<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class ModuleDevCommand extends Command
{
    protected $signature = 'module:dev
                            {action : Action to perform (watch|link|unlink|info)}
                            {module? : Module name for specific actions}';

    protected $description = 'Development utilities for modules';

    public function __construct(
        private ModuleManagerInterface $moduleManager,
        private Filesystem $files
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'watch' => $this->watchModules(),
            'link' => $this->linkModule(),
            'unlink' => $this->unlinkModule(),
            'info' => $this->showDevInfo(),
            default => $this->showUsage()
        };
    }

    private function watchModules(): int
    {
        $this->info('🔍 Starting module file watcher...');
        $this->info('Press Ctrl+C to stop watching');

        $modulesPath = config('modular-ddd.modules_path', base_path('modules'));

        if (!$this->files->isDirectory($modulesPath)) {
            $this->error("Modules directory not found: {$modulesPath}");
            return self::FAILURE;
        }

        $lastModified = [];

        while (true) {
            $modules = $this->moduleManager->list()
                ->filter(fn($module) => $module->isEnabled());

            foreach ($modules as $module) {
                $this->checkModuleChanges($module, $lastModified);
            }

            sleep(2); // Check every 2 seconds
        }
    }

    private function checkModuleChanges($module, array &$lastModified): void
    {
        $moduleFiles = $this->getModuleFiles($module->path);
        $currentModified = [];

        foreach ($moduleFiles as $file) {
            if ($this->files->exists($file)) {
                $currentModified[$file] = $this->files->lastModified($file);
            }
        }

        $moduleName = $module->name;

        if (!isset($lastModified[$moduleName])) {
            $lastModified[$moduleName] = $currentModified;
            return;
        }

        $changes = array_diff_assoc($currentModified, $lastModified[$moduleName]);
        $newFiles = array_diff_key($currentModified, $lastModified[$moduleName]);
        $deletedFiles = array_diff_key($lastModified[$moduleName], $currentModified);

        if (!empty($changes) || !empty($newFiles) || !empty($deletedFiles)) {
            $this->line(now()->format('H:i:s') . " - Changes detected in {$moduleName}:");

            foreach ($changes as $file => $time) {
                $relativePath = str_replace($module->path . '/', '', $file);
                $this->line("  📝 Modified: {$relativePath}");
            }

            foreach ($newFiles as $file => $time) {
                $relativePath = str_replace($module->path . '/', '', $file);
                $this->line("  ➕ Added: {$relativePath}");
            }

            foreach ($deletedFiles as $file => $time) {
                $relativePath = str_replace($module->path . '/', '', $file);
                $this->line("  ❌ Deleted: {$relativePath}");
            }

            // Auto-reload module if enabled in config
            if (config('modular-ddd.development.auto_reload', false)) {
                $this->line("  🔄 Auto-reloading module...");
                $this->reloadModule($moduleName);
            }

            $lastModified[$moduleName] = $currentModified;
        }
    }

    private function getModuleFiles(string $modulePath): array
    {
        $files = [];
        $extensions = ['php', 'json', 'blade.php', 'js', 'vue', 'css', 'scss'];

        foreach ($extensions as $ext) {
            $pattern = $ext === 'blade.php'
                ? "{$modulePath}/**/*.blade.php"
                : "{$modulePath}/**/*.{$ext}";

            $found = glob($pattern, GLOB_BRACE);
            if ($found) {
                $files = array_merge($files, $found);
            }
        }

        return $files;
    }

    private function reloadModule(string $moduleName): void
    {
        try {
            // Clear caches
            $this->call('module:cache', ['action' => 'clear']);

            // Disable and re-enable module
            $this->moduleManager->disable($moduleName);
            $this->moduleManager->enable($moduleName);

            $this->line("  ✅ Module {$moduleName} reloaded successfully");
        } catch (\Exception $e) {
            $this->line("  ❌ Failed to reload module: " . $e->getMessage());
        }
    }

    private function linkModule(): int
    {
        $moduleName = $this->argument('module');

        if (!$moduleName) {
            $this->error('Module name is required for link action');
            return self::FAILURE;
        }

        $module = $this->moduleManager->getInfo($moduleName);
        if (!$module) {
            $this->error("Module '{$moduleName}' not found");
            return self::FAILURE;
        }

        $this->info("🔗 Creating development symlinks for module: {$moduleName}");

        try {
            // Link assets if they exist
            $this->linkAssets($module);

            // Link views if they exist
            $this->linkViews($module);

            // Link config files if they exist
            $this->linkConfig($module);

            $this->info("✅ Development links created for {$moduleName}");
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed to create links: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function linkAssets($module): void
    {
        $assetsPath = $module->path . '/Resources/assets';
        $publicPath = public_path("modules/{$module->name}");

        if ($this->files->isDirectory($assetsPath)) {
            if (!$this->files->exists($publicPath)) {
                $this->files->link($assetsPath, $publicPath);
                $this->line("  🎨 Linked assets: {$assetsPath} -> {$publicPath}");
            }
        }
    }

    private function linkViews($module): void
    {
        $viewsPath = $module->path . '/Resources/views';
        $laravelViewsPath = resource_path("views/modules/{$module->name}");

        if ($this->files->isDirectory($viewsPath)) {
            $parentDir = dirname($laravelViewsPath);
            if (!$this->files->exists($parentDir)) {
                $this->files->makeDirectory($parentDir, 0755, true);
            }

            if (!$this->files->exists($laravelViewsPath)) {
                $this->files->link($viewsPath, $laravelViewsPath);
                $this->line("  👁️  Linked views: {$viewsPath} -> {$laravelViewsPath}");
            }
        }
    }

    private function linkConfig($module): void
    {
        $configPath = $module->path . '/Config';
        $laravelConfigPath = config_path("modules/{$module->name}");

        if ($this->files->isDirectory($configPath)) {
            $parentDir = dirname($laravelConfigPath);
            if (!$this->files->exists($parentDir)) {
                $this->files->makeDirectory($parentDir, 0755, true);
            }

            if (!$this->files->exists($laravelConfigPath)) {
                $this->files->link($configPath, $laravelConfigPath);
                $this->line("  ⚙️  Linked config: {$configPath} -> {$laravelConfigPath}");
            }
        }
    }

    private function unlinkModule(): int
    {
        $moduleName = $this->argument('module');

        if (!$moduleName) {
            $this->error('Module name is required for unlink action');
            return self::FAILURE;
        }

        $this->info("🔗 Removing development symlinks for module: {$moduleName}");

        try {
            $links = [
                public_path("modules/{$moduleName}"),
                resource_path("views/modules/{$moduleName}"),
                config_path("modules/{$moduleName}"),
            ];

            foreach ($links as $link) {
                if ($this->files->exists($link) && is_link($link)) {
                    $this->files->delete($link);
                    $this->line("  ❌ Removed link: {$link}");
                }
            }

            $this->info("✅ Development links removed for {$moduleName}");
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed to remove links: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function showDevInfo(): int
    {
        $this->info('🛠️  Module Development Information');
        $this->newLine();

        // Show development configuration
        $config = config('modular-ddd.development', []);
        $this->line('📋 Development Configuration:');
        $this->line('   Auto-reload: ' . ($config['auto_reload'] ?? false ? 'Enabled' : 'Disabled'));
        $this->line('   Hot-reload: ' . ($config['hot_reload'] ?? false ? 'Enabled' : 'Disabled'));

        $this->newLine();

        // Show linked modules
        $this->line('🔗 Linked Modules:');
        $modules = $this->moduleManager->list();
        $hasLinks = false;

        foreach ($modules as $module) {
            $links = $this->getModuleLinks($module->name);
            if (!empty($links)) {
                $hasLinks = true;
                $this->line("   📦 {$module->name}:");
                foreach ($links as $type => $path) {
                    $this->line("      {$type}: {$path}");
                }
            }
        }

        if (!$hasLinks) {
            $this->line('   No modules currently linked for development');
        }

        $this->newLine();

        // Show useful commands
        $this->line('🚀 Useful Development Commands:');
        $this->line('   php artisan module:dev watch     - Watch for file changes');
        $this->line('   php artisan module:dev link {module}   - Create dev symlinks');
        $this->line('   php artisan module:dev unlink {module} - Remove dev symlinks');
        $this->line('   php artisan module:health --all  - Check module health');
        $this->line('   php artisan module:cache rebuild - Rebuild module cache');

        return self::SUCCESS;
    }

    private function getModuleLinks(string $moduleName): array
    {
        $links = [];

        $possibleLinks = [
            'assets' => public_path("modules/{$moduleName}"),
            'views' => resource_path("views/modules/{$moduleName}"),
            'config' => config_path("modules/{$moduleName}"),
        ];

        foreach ($possibleLinks as $type => $path) {
            if ($this->files->exists($path) && is_link($path)) {
                $links[$type] = $path;
            }
        }

        return $links;
    }

    private function showUsage(): int
    {
        $this->error('Invalid action specified');
        $this->line('Available actions:');
        $this->line('  watch  - Watch modules for file changes');
        $this->line('  link   - Create development symlinks for a module');
        $this->line('  unlink - Remove development symlinks for a module');
        $this->line('  info   - Show development information');

        return self::FAILURE;
    }
}