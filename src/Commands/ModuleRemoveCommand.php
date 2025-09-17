<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Exception;
use Illuminate\Console\Command;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\Exceptions\ModuleInstallationException;
use TaiCrm\LaravelModularDdd\Exceptions\ModuleNotFoundException;

class ModuleRemoveCommand extends Command
{
    protected $signature = 'module:remove {name : The name of the module to remove} {--force : Force removal even if other modules depend on it}';
    protected $description = 'Remove a module completely';

    public function __construct(
        private ModuleManagerInterface $moduleManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $moduleName = $this->argument('name');

        if (!$this->moduleManager->isInstalled($moduleName)) {
            $this->info("✅ Module '{$moduleName}' is not installed.");

            return self::SUCCESS;
        }

        $this->warn("⚠️  This will completely remove module '{$moduleName}' and all its data!");

        try {
            // Show module info and dependents
            $this->showRemovalInfo($moduleName);

            if (!$this->option('force') && !$this->confirmRemoval($moduleName)) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }

            // Check for dependents
            if (!$this->option('force')) {
                $this->checkDependents($moduleName);
            }

            // Remove the module
            $success = $this->moduleManager->remove($moduleName);

            if ($success) {
                $this->info("✅ Module '{$moduleName}' removed successfully.");
            } else {
                $this->error("❌ Failed to remove module '{$moduleName}'.");

                return self::FAILURE;
            }
        } catch (ModuleNotFoundException $e) {
            $this->error('❌ ' . $e->getMessage());

            return self::FAILURE;
        } catch (ModuleInstallationException $e) {
            $this->error('❌ Removal error: ' . $e->getMessage());

            return self::FAILURE;
        } catch (Exception $e) {
            $this->error('❌ Unexpected error: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function showRemovalInfo(string $moduleName): void
    {
        $moduleInfo = $this->moduleManager->getInfo($moduleName);

        if ($moduleInfo) {
            $this->newLine();
            $this->line('📦 <comment>Module to remove:</comment>');
            $this->line("   <info>{$moduleInfo->displayName}</info> v{$moduleInfo->version}");
            $this->line("   {$moduleInfo->description}");
            $this->line("   <comment>State:</comment> {$moduleInfo->state->getDisplayName()}");
        }

        // Show dependents
        $dependents = $this->moduleManager->getDependents($moduleName);

        if ($dependents->isNotEmpty()) {
            $this->newLine();
            $this->warn("⚠️  <comment>Modules that depend on '{$moduleName}':</comment>");

            foreach ($dependents as $dependent) {
                $isInstalled = $this->moduleManager->isInstalled($dependent);
                $status = $isInstalled ? '<error>✗ Will be broken</error>' : '<comment>○ Not installed</comment>';
                $this->line("  • {$dependent} {$status}");
            }

            $this->newLine();
            $this->error('🚨 Removing this module will break dependent modules!');
        }

        $this->newLine();
        $this->warn('This action will:');
        $this->line('  • Remove all module files');
        $this->line('  • Rollback database migrations');
        $this->line('  • Delete module data');
        $this->line('  • Clear module cache');
        $this->error('  • THIS CANNOT BE UNDONE!');
    }

    private function confirmRemoval(string $moduleName): bool
    {
        $this->newLine();

        // Double confirmation for safety
        if (!$this->confirm("Are you sure you want to remove '{$moduleName}'?", false)) {
            return false;
        }

        return $this->confirm('Type the module name to confirm removal:', false) === $moduleName
               || $this->ask("Type '{$moduleName}' to confirm:") === $moduleName;
    }

    private function checkDependents(string $moduleName): void
    {
        $dependents = $this->moduleManager->getDependents($moduleName);
        $installedDependents = $dependents->filter(fn ($dep) => $this->moduleManager->isInstalled($dep));

        if ($installedDependents->isNotEmpty()) {
            $dependentList = $installedDependents->implode(', ');

            throw ModuleInstallationException::cannotRemove(
                $moduleName,
                "Module has installed dependents: {$dependentList}. Use --force to override.",
            );
        }
    }
}
