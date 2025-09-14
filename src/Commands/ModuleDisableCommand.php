<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\Exceptions\ModuleNotFoundException;
use TaiCrm\LaravelModularDdd\Exceptions\ModuleInstallationException;
use Illuminate\Console\Command;

class ModuleDisableCommand extends Command
{
    protected $signature = 'module:disable {name : The name of the module to disable} {--force : Force disable even if other modules depend on it}';

    protected $description = 'Disable a module';

    public function __construct(
        private ModuleManagerInterface $moduleManager
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $moduleName = $this->argument('name');

        if (!$this->moduleManager->isEnabled($moduleName)) {
            $this->info("✅ Module '{$moduleName}' is already disabled.");
            return self::SUCCESS;
        }

        $this->info("Disabling module: {$moduleName}");

        try {
            // Show dependents that will be affected
            $this->showDependentInfo($moduleName);

            if (!$this->option('force') && !$this->confirmDisabling($moduleName)) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }

            // Check for dependents
            if (!$this->option('force')) {
                $this->checkDependents($moduleName);
            }

            // Disable the module
            $success = $this->moduleManager->disable($moduleName);

            if ($success) {
                $this->info("✅ Module '{$moduleName}' disabled successfully.");
                $this->showModuleInfo($moduleName);
            } else {
                $this->error("❌ Failed to disable module '{$moduleName}'.");
                return self::FAILURE;
            }

        } catch (ModuleNotFoundException $e) {
            $this->error("❌ " . $e->getMessage());
            return self::FAILURE;

        } catch (ModuleInstallationException $e) {
            $this->error("❌ Disable error: " . $e->getMessage());
            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error("❌ Unexpected error: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function showDependentInfo(string $moduleName): void
    {
        $dependents = $this->moduleManager->getDependents($moduleName);

        if ($dependents->isNotEmpty()) {
            $this->newLine();
            $this->warn("⚠️  <comment>Modules that depend on '{$moduleName}':</comment>");

            foreach ($dependents as $dependent) {
                $isEnabled = $this->moduleManager->isEnabled($dependent);
                $status = $isEnabled ? '<error>✗ Will be affected</error>' : '<comment>○ Not enabled</comment>';
                $this->line("  • {$dependent} {$status}");
            }

            $this->newLine();
            $this->warn("Disabling this module may break dependent functionality.");
        }
    }

    private function confirmDisabling(string $moduleName): bool
    {
        return $this->confirm("Do you want to disable '{$moduleName}'?", true);
    }

    private function checkDependents(string $moduleName): void
    {
        $dependents = $this->moduleManager->getDependents($moduleName);
        $enabledDependents = $dependents->filter(fn($dep) => $this->moduleManager->isEnabled($dep));

        if ($enabledDependents->isNotEmpty()) {
            $dependentList = $enabledDependents->implode(', ');
            throw ModuleInstallationException::cannotDisable(
                $moduleName,
                "Module has enabled dependents: {$dependentList}. Use --force to override."
            );
        }
    }

    private function showModuleInfo(string $moduleName): void
    {
        $moduleInfo = $this->moduleManager->getInfo($moduleName);

        if ($moduleInfo) {
            $this->newLine();
            $this->line("📦 <info>{$moduleInfo->displayName}</info> v{$moduleInfo->version}");
            $this->line("   {$moduleInfo->description}");
            $this->line("   <comment>State:</comment> <comment>Disabled</comment>");
        }
    }
}