<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Exception;
use Illuminate\Console\Command;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\Exceptions\DependencyException;
use TaiCrm\LaravelModularDdd\Exceptions\ModuleInstallationException;
use TaiCrm\LaravelModularDdd\Exceptions\ModuleNotFoundException;

class ModuleInstallCommand extends Command
{
    protected $signature = 'module:install {name : The name of the module to install} {--force : Force installation even if dependencies are missing} {--yes : Answer yes to all prompts}';
    protected $description = 'Install a module';

    public function __construct(
        private ModuleManagerInterface $moduleManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $moduleName = $this->argument('name');

        if ($this->moduleManager->isInstalled($moduleName)) {
            $this->warn("Module '{$moduleName}' is already installed.");

            return self::SUCCESS;
        }

        $this->info("Installing module: {$moduleName}");

        try {
            // Show module info
            $moduleInfo = $this->moduleManager->getInfo($moduleName);
            if ($moduleInfo) {
                $this->displayModuleInfo($moduleInfo);

                if (!$this->option('force') && !$this->option('yes') && !$this->input->getOption('no-interaction') && !$this->confirmInstallation($moduleInfo)) {
                    $this->info('Installation cancelled.');

                    return self::SUCCESS;
                }
            }

            // Validate dependencies
            if (!$this->option('force')) {
                $this->validateDependencies($moduleName);
            }

            // Perform installation
            $success = $this->moduleManager->install($moduleName);

            if ($success) {
                $this->info("✅ Module '{$moduleName}' installed successfully.");

                $shouldEnable = $this->option('yes') || $this->input->getOption('no-interaction') || $this->confirm('Would you like to enable the module now?', true);
                if ($shouldEnable) {
                    return $this->call('module:enable', ['name' => $moduleName]);
                }
            } else {
                $this->error("❌ Failed to install module '{$moduleName}'.");

                return self::FAILURE;
            }
        } catch (ModuleNotFoundException $e) {
            $this->error("❌ Module '{$moduleName}' not found.");
            $this->suggestSimilarModules($moduleName);

            return self::FAILURE;
        } catch (DependencyException $e) {
            $this->error('❌ Dependency error: ' . $e->getMessage());

            return self::FAILURE;
        } catch (ModuleInstallationException $e) {
            $this->error('❌ Installation error: ' . $e->getMessage());

            return self::FAILURE;
        } catch (Exception $e) {
            $this->error('❌ Unexpected error: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function displayModuleInfo($moduleInfo): void
    {
        $this->newLine();
        $this->line("📦 <info>{$moduleInfo->displayName}</info> v{$moduleInfo->version}");
        $this->line("   {$moduleInfo->description}");

        if (!empty($moduleInfo->dependencies)) {
            $this->line('   <comment>Dependencies:</comment> ' . implode(', ', $moduleInfo->dependencies));
        }

        if (!empty($moduleInfo->optionalDependencies)) {
            $this->line('   <comment>Optional:</comment> ' . implode(', ', $moduleInfo->optionalDependencies));
        }

        $this->newLine();
    }

    private function confirmInstallation($moduleInfo): bool
    {
        return $this->confirm(
            "Do you want to install '{$moduleInfo->displayName}' v{$moduleInfo->version}?",
            true,
        );
    }

    private function validateDependencies(string $moduleName): void
    {
        try {
            $this->moduleManager->validateDependencies($moduleName);
        } catch (DependencyException $e) {
            $this->warn('⚠️  ' . $e->getMessage());

            if (!$this->option('yes') && !$this->input->getOption('no-interaction') && !$this->confirm('Do you want to continue anyway?', false)) {
                throw $e;
            }
        }
    }

    private function suggestSimilarModules(string $moduleName): void
    {
        $allModules = $this->moduleManager->list();
        $suggestions = $allModules->filter(static fn ($module) => levenshtein($module->name, $moduleName) <= 3)->pluck('name')->take(3);

        if ($suggestions->isNotEmpty()) {
            $this->newLine();
            $this->line('Did you mean one of these?');
            foreach ($suggestions as $suggestion) {
                $this->line("  • {$suggestion}");
            }
        }
    }
}
