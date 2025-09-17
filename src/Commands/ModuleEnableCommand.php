<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Exception;
use Illuminate\Console\Command;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\Exceptions\DependencyException;
use TaiCrm\LaravelModularDdd\Exceptions\ModuleInstallationException;
use TaiCrm\LaravelModularDdd\Exceptions\ModuleNotFoundException;

class ModuleEnableCommand extends Command
{
    protected $signature = 'module:enable {name : The name of the module to enable} {--force : Force enable even if dependencies are missing}';
    protected $description = 'Enable a module';

    public function __construct(
        private ModuleManagerInterface $moduleManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $moduleName = $this->argument('name');

        if (!$this->moduleManager->isInstalled($moduleName)) {
            $this->error("❌ Module '{$moduleName}' is not installed.");

            if ($this->confirm('Would you like to install it first?', true)) {
                return $this->call('module:install', ['name' => $moduleName]);
            }

            return self::FAILURE;
        }

        if ($this->moduleManager->isEnabled($moduleName)) {
            $this->info("✅ Module '{$moduleName}' is already enabled.");

            return self::SUCCESS;
        }

        $this->info("Enabling module: {$moduleName}");

        try {
            // Show dependencies that will be enabled
            $this->showDependencyInfo($moduleName);

            if (!$this->option('force') && !$this->confirmEnabling($moduleName)) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }

            // Validate dependencies
            if (!$this->option('force')) {
                $this->validateDependencies($moduleName);
            }

            // Enable the module
            $success = $this->moduleManager->enable($moduleName);

            if ($success) {
                $this->info("✅ Module '{$moduleName}' enabled successfully.");
                $this->showModuleInfo($moduleName);
            } else {
                $this->error("❌ Failed to enable module '{$moduleName}'.");

                return self::FAILURE;
            }
        } catch (ModuleNotFoundException $e) {
            $this->error('❌ ' . $e->getMessage());

            return self::FAILURE;
        } catch (DependencyException $e) {
            $this->error('❌ Dependency error: ' . $e->getMessage());

            return self::FAILURE;
        } catch (ModuleInstallationException $e) {
            $this->error('❌ Enable error: ' . $e->getMessage());

            return self::FAILURE;
        } catch (Exception $e) {
            $this->error('❌ Unexpected error: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function showDependencyInfo(string $moduleName): void
    {
        $dependencies = $this->moduleManager->getDependencies($moduleName);

        if ($dependencies->isNotEmpty()) {
            $this->newLine();
            $this->line('📋 <comment>Dependencies that will be enabled:</comment>');

            foreach ($dependencies as $dependency) {
                $isEnabled = $this->moduleManager->isEnabled($dependency);
                $status = $isEnabled ? '<info>✓ Enabled</info>' : '<comment>○ Will be enabled</comment>';
                $this->line("  • {$dependency} {$status}");
            }
        }
    }

    private function confirmEnabling(string $moduleName): bool
    {
        return $this->confirm("Do you want to enable '{$moduleName}'?", true);
    }

    private function validateDependencies(string $moduleName): void
    {
        try {
            $this->moduleManager->validateDependencies($moduleName);
        } catch (DependencyException $e) {
            $this->warn('⚠️  ' . $e->getMessage());

            if (!$this->confirm('Do you want to continue anyway?', false)) {
                throw $e;
            }
        }
    }

    private function showModuleInfo(string $moduleName): void
    {
        $moduleInfo = $this->moduleManager->getInfo($moduleName);

        if ($moduleInfo) {
            $this->newLine();
            $this->line("📦 <info>{$moduleInfo->displayName}</info> v{$moduleInfo->version}");
            $this->line("   {$moduleInfo->description}");
            $this->line('   <comment>State:</comment> <info>Enabled</info>');
        }
    }
}
