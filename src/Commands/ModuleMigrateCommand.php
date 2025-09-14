<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\Exceptions\ModuleNotFoundException;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;

class ModuleMigrateCommand extends Command
{
    protected $signature = 'module:migrate
                            {module? : The module to migrate}
                            {--all : Migrate all enabled modules}
                            {--rollback : Rollback migrations}
                            {--step=1 : Number of steps to rollback}
                            {--force : Force migration in production}';

    protected $description = 'Run migrations for modules';

    public function __construct(
        private ModuleManagerInterface $moduleManager,
        private Migrator $migrator,
        private Filesystem $files
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->migrateAllModules();
        }

        $moduleName = $this->argument('module');
        if (!$moduleName) {
            $this->error('Please specify a module name or use --all flag');
            return self::FAILURE;
        }

        return $this->migrateModule($moduleName);
    }

    private function migrateAllModules(): int
    {
        $modules = $this->moduleManager->list()
            ->filter(fn($module) => $module->isEnabled());

        if ($modules->isEmpty()) {
            $this->info('No enabled modules found.');
            return self::SUCCESS;
        }

        $this->info('🔄 Running migrations for all enabled modules...');
        $success = true;

        foreach ($modules as $module) {
            $this->newLine();
            $this->line("📦 Migrating module: {$module->name}");

            $result = $this->runModuleMigrations($module->name, $module->path);
            if ($result !== self::SUCCESS) {
                $success = false;
            }
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }

    private function migrateModule(string $moduleName): int
    {
        try {
            $module = $this->moduleManager->getInfo($moduleName);

            if (!$module) {
                throw new ModuleNotFoundException($moduleName);
            }

            if (!$module->isEnabled()) {
                $this->warn("⚠️  Module '{$moduleName}' is not enabled. Enable it first with: php artisan module:enable {$moduleName}");
                return self::FAILURE;
            }

            $this->info("🔄 Running migrations for module: {$moduleName}");

            return $this->runModuleMigrations($moduleName, $module->path);

        } catch (ModuleNotFoundException $e) {
            $this->error("❌ " . $e->getMessage());
            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error("❌ Migration failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function runModuleMigrations(string $moduleName, string $modulePath): int
    {
        $migrationsPath = $modulePath . '/Database/Migrations';

        if (!$this->files->isDirectory($migrationsPath)) {
            $this->line("   📁 No migrations directory found, skipping.");
            return self::SUCCESS;
        }

        $migrationFiles = $this->files->glob($migrationsPath . '/*.php');

        if (empty($migrationFiles)) {
            $this->line("   📁 No migration files found, skipping.");
            return self::SUCCESS;
        }

        try {
            if ($this->option('rollback')) {
                return $this->rollbackMigrations($migrationsPath);
            } else {
                return $this->runMigrations($migrationsPath);
            }

        } catch (\Exception $e) {
            $this->error("   ❌ Migration error: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function runMigrations(string $path): int
    {
        $exitCode = $this->call('migrate', [
            '--path' => $this->getRelativePath($path),
            '--force' => $this->option('force'),
            '--no-interaction' => true,
        ]);

        if ($exitCode === 0) {
            $this->line("   ✅ Migrations completed successfully");
        }

        return $exitCode;
    }

    private function rollbackMigrations(string $path): int
    {
        $exitCode = $this->call('migrate:rollback', [
            '--path' => $this->getRelativePath($path),
            '--step' => $this->option('step'),
            '--force' => $this->option('force'),
            '--no-interaction' => true,
        ]);

        if ($exitCode === 0) {
            $this->line("   ✅ Migrations rolled back successfully");
        }

        return $exitCode;
    }

    private function getRelativePath(string $absolutePath): string
    {
        $basePath = base_path();

        if (str_starts_with($absolutePath, $basePath)) {
            return substr($absolutePath, strlen($basePath) + 1);
        }

        return $absolutePath;
    }
}