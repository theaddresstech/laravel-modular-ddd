<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\Exceptions\ModuleNotFoundException;
use Illuminate\Console\Command;

class ModuleStatusCommand extends Command
{
    protected $signature = 'module:status {name : The name of the module to check}';

    protected $description = 'Show detailed status information for a module';

    public function __construct(
        private ModuleManagerInterface $moduleManager
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $moduleName = $this->argument('name');

        try {
            $moduleInfo = $this->moduleManager->getInfo($moduleName);

            if (!$moduleInfo) {
                throw new ModuleNotFoundException($moduleName);
            }

            $this->displayModuleStatus($moduleInfo);
            $this->displayDependencyInfo($moduleName);

            return self::SUCCESS;

        } catch (ModuleNotFoundException $e) {
            $this->error("❌ " . $e->getMessage());
            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error("❌ Unexpected error: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function displayModuleStatus($moduleInfo): void
    {
        $this->newLine();
        $this->line("📦 <info>{$moduleInfo->displayName}</info>");

        $statusColor = $moduleInfo->state->getColor();
        $statusText = $moduleInfo->state->getDisplayName();

        $this->table(['Property', 'Value'], [
            ['Name', $moduleInfo->name],
            ['Display Name', $moduleInfo->displayName],
            ['Version', $moduleInfo->version],
            ['Author', $moduleInfo->author ?: 'Unknown'],
            ['Status', "<fg={$statusColor}>{$statusText}</>"],
            ['Path', $moduleInfo->path],
            ['Description', $this->wrapText($moduleInfo->description, 50)],
        ]);
    }

    private function displayDependencyInfo(string $moduleName): void
    {
        $dependencies = $this->moduleManager->getDependencies($moduleName);
        $dependents = $this->moduleManager->getDependents($moduleName);

        if ($dependencies->isNotEmpty()) {
            $this->newLine();
            $this->line("📋 <comment>Dependencies:</comment>");

            $depRows = [];
            foreach ($dependencies as $dependency) {
                $isInstalled = $this->moduleManager->isInstalled($dependency);
                $isEnabled = $this->moduleManager->isEnabled($dependency);

                $status = match (true) {
                    $isEnabled => '<info>✓ Enabled</info>',
                    $isInstalled => '<comment>○ Installed</comment>',
                    default => '<error>✗ Missing</error>'
                };

                $depRows[] = [$dependency, $status];
            }

            $this->table(['Module', 'Status'], $depRows);
        }

        if ($dependents->isNotEmpty()) {
            $this->newLine();
            $this->line("📋 <comment>Dependents (modules that depend on this):</comment>");

            $depRows = [];
            foreach ($dependents as $dependent) {
                $isInstalled = $this->moduleManager->isInstalled($dependent);
                $isEnabled = $this->moduleManager->isEnabled($dependent);

                $status = match (true) {
                    $isEnabled => '<info>✓ Enabled</info>',
                    $isInstalled => '<comment>○ Installed</comment>',
                    default => '<fg=gray>○ Not Installed</>'
                };

                $depRows[] = [$dependent, $status];
            }

            $this->table(['Module', 'Status'], $depRows);
        }

        if ($dependencies->isEmpty() && $dependents->isEmpty()) {
            $this->newLine();
            $this->line("📋 <comment>No dependencies or dependents found.</comment>");
        }
    }

    private function wrapText(string $text, int $width): string
    {
        if (strlen($text) <= $width) {
            return $text;
        }

        return substr($text, 0, $width - 3) . '...';
    }
}