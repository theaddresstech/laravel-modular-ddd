<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;
use Illuminate\Console\Command;

class ModuleListCommand extends Command
{
    protected $signature = 'module:list {--enabled : Show only enabled modules} {--disabled : Show only disabled modules}';

    protected $description = 'List all available modules';

    public function __construct(
        private ModuleManagerInterface $moduleManager
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $modules = $this->moduleManager->list();

        if ($modules->isEmpty()) {
            $this->info('No modules found.');
            return self::SUCCESS;
        }

        // Filter modules based on options
        if ($this->option('enabled')) {
            $modules = $modules->filter(fn(ModuleInfo $module) => $module->isEnabled());
        } elseif ($this->option('disabled')) {
            $modules = $modules->filter(fn(ModuleInfo $module) => !$module->isEnabled());
        }

        $this->displayModules($modules);

        return self::SUCCESS;
    }

    private function displayModules($modules): void
    {
        $headers = ['Name', 'Display Name', 'Version', 'State', 'Description'];
        $rows = [];

        foreach ($modules as $module) {
            $rows[] = [
                $module->name,
                $module->displayName,
                $module->version,
                $this->formatState($module->state),
                $this->truncateText($module->description, 50),
            ];
        }

        $this->table($headers, $rows);

        $this->newLine();
        $this->info("Total modules: " . $modules->count());

        // Show state counts
        $states = $modules->groupBy(fn(ModuleInfo $module) => $module->state->value);
        foreach ($states as $state => $moduleGroup) {
            $count = $moduleGroup->count();
            $displayState = ucwords(str_replace('_', ' ', $state));
            $this->line("  <info>{$displayState}:</info> {$count}");
        }
    }

    private function formatState($state): string
    {
        $color = $state->getColor();
        $displayName = $state->getDisplayName();

        return "<fg={$color}>{$displayName}</>";
    }

    private function truncateText(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3) . '...';
    }
}