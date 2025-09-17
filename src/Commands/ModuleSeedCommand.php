<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\Exceptions\ModuleNotFoundException;

class ModuleSeedCommand extends Command
{
    protected $signature = 'module:seed
                            {module? : The module to seed}
                            {--all : Seed all enabled modules}
                            {--class= : Specific seeder class to run}';
    protected $description = 'Run seeders for modules';

    public function __construct(
        private ModuleManagerInterface $moduleManager,
        private Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->seedAllModules();
        }

        $moduleName = $this->argument('module');
        if (!$moduleName) {
            $this->error('Please specify a module name or use --all flag');

            return self::FAILURE;
        }

        return $this->seedModule($moduleName);
    }

    private function seedAllModules(): int
    {
        $modules = $this->moduleManager->list()
            ->filter(static fn ($module) => $module->isEnabled());

        if ($modules->isEmpty()) {
            $this->info('No enabled modules found.');

            return self::SUCCESS;
        }

        $this->info('🌱 Running seeders for all enabled modules...');
        $success = true;

        foreach ($modules as $module) {
            $this->newLine();
            $this->line("📦 Seeding module: {$module->name}");

            $result = $this->runModuleSeeders($module->name, $module->path);
            if ($result !== self::SUCCESS) {
                $success = false;
            }
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }

    private function seedModule(string $moduleName): int
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

            $this->info("🌱 Running seeders for module: {$moduleName}");

            return $this->runModuleSeeders($moduleName, $module->path);
        } catch (ModuleNotFoundException $e) {
            $this->error('❌ ' . $e->getMessage());

            return self::FAILURE;
        } catch (Exception $e) {
            $this->error('❌ Seeding failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function runModuleSeeders(string $moduleName, string $modulePath): int
    {
        $seedersPath = $modulePath . '/Database/Seeders';

        if (!$this->files->isDirectory($seedersPath)) {
            $this->line('   📁 No seeders directory found, skipping.');

            return self::SUCCESS;
        }

        $seederFiles = $this->files->glob($seedersPath . '/*.php');

        if (empty($seederFiles)) {
            $this->line('   📁 No seeder files found, skipping.');

            return self::SUCCESS;
        }

        try {
            if ($this->option('class')) {
                return $this->runSpecificSeeder($seedersPath, $this->option('class'));
            }

            return $this->runAllSeeders($seedersPath, $moduleName);
        } catch (Exception $e) {
            $this->error('   ❌ Seeding error: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function runSpecificSeeder(string $seedersPath, string $className): int
    {
        $seederFile = $seedersPath . '/' . $className . '.php';

        if (!$this->files->exists($seederFile)) {
            $this->error("   ❌ Seeder class '{$className}' not found");

            return self::FAILURE;
        }

        $this->line("   🌱 Running seeder: {$className}");

        $exitCode = $this->call('db:seed', [
            '--class' => $this->getSeederClass($seedersPath, $className),
            '--no-interaction' => true,
        ]);

        if ($exitCode === 0) {
            $this->line('   ✅ Seeder completed successfully');
        }

        return $exitCode;
    }

    private function runAllSeeders(string $seedersPath, string $moduleName): int
    {
        $seederFiles = $this->files->glob($seedersPath . '/*.php');
        $success = true;

        foreach ($seederFiles as $seederFile) {
            $className = pathinfo($seederFile, PATHINFO_FILENAME);

            // Skip abstract classes and base seeders
            if (str_starts_with($className, 'Abstract') || $className === 'DatabaseSeeder') {
                continue;
            }

            $this->line("   🌱 Running seeder: {$className}");

            try {
                $exitCode = $this->call('db:seed', [
                    '--class' => $this->getSeederClass($seedersPath, $className),
                    '--no-interaction' => true,
                ]);

                if ($exitCode === 0) {
                    $this->line("   ✅ Seeder '{$className}' completed successfully");
                } else {
                    $this->error("   ❌ Seeder '{$className}' failed");
                    $success = false;
                }
            } catch (Exception $e) {
                $this->error("   ❌ Error running seeder '{$className}': " . $e->getMessage());
                $success = false;
            }
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }

    private function getSeederClass(string $seedersPath, string $className): string
    {
        // Try to determine the full class name
        $content = $this->files->get($seedersPath . '/' . $className . '.php');

        if (preg_match('/namespace\s+(.+?);/', $content, $matches)) {
            return $matches[1] . '\\' . $className;
        }

        return $className;
    }
}
