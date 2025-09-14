<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use Illuminate\Console\Command;

class ModuleCacheCommand extends Command
{
    protected $signature = 'module:cache {action=rebuild : Action to perform (clear|rebuild)}';

    protected $description = 'Manage module cache (clear or rebuild)';

    public function __construct(
        private ModuleManagerInterface $moduleManager
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        return match (strtolower($action)) {
            'clear' => $this->clearCache(),
            'rebuild' => $this->rebuildCache(),
            default => $this->invalidAction($action)
        };
    }

    private function clearCache(): int
    {
        try {
            $this->info('Clearing module cache...');

            $this->moduleManager->clearCache();

            $this->info('✅ Module cache cleared successfully.');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed to clear cache: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function rebuildCache(): int
    {
        try {
            $this->info('Rebuilding module cache...');

            // Clear first
            $this->moduleManager->clearCache();

            // Then rebuild by loading modules
            $modules = $this->moduleManager->list();

            $this->info("✅ Module cache rebuilt successfully.");
            $this->line("   Cached {$modules->count()} modules");

            // Show summary
            $this->displayCacheSummary($modules);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed to rebuild cache: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function displayCacheSummary($modules): void
    {
        if ($modules->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->line('📊 <comment>Cache Summary:</comment>');

        $states = $modules->groupBy(fn($module) => $module->state->value);

        foreach ($states as $state => $moduleGroup) {
            $count = $moduleGroup->count();
            $displayState = ucwords(str_replace('_', ' ', $state));
            $this->line("   <info>{$displayState}:</info> {$count}");
        }

        $this->newLine();
        $this->line("Cache includes module manifests, dependencies, and metadata.");
    }

    private function invalidAction(string $action): int
    {
        $this->error("❌ Invalid action: {$action}");
        $this->line('Available actions: clear, rebuild');
        return self::FAILURE;
    }
}