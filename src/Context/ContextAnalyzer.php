<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Context;

use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

/**
 * Analyzes modules to determine which contexts they support
 */
class ContextAnalyzer
{
    private const CONTEXT_INDICATORS = [
        ModuleContext::API->value => [
            'routes/api.php',
            'Http/Controllers/Api/',
            'Http/Resources/',
            'Http/Requests/Api/',
        ],
        ModuleContext::WEB->value => [
            'routes/web.php',
            'Http/Controllers/Web/',
            'resources/views/',
            'Http/Requests/Web/',
        ],
        ModuleContext::CLI->value => [
            'Console/Commands/',
            'Console/Kernel.php',
            'Commands/',
        ],
        ModuleContext::ADMIN->value => [
            'Http/Controllers/Admin/',
            'Http/Middleware/Admin/',
            'resources/views/admin/',
            'Policies/Admin/',
        ],
        ModuleContext::TESTING->value => [
            'Tests/',
            'tests/',
            'TestCase.php',
        ],
        ModuleContext::QUEUE->value => [
            'Jobs/',
            'Queue/',
            'Listeners/',
            'Notifications/',
        ],
        ModuleContext::BROADCAST->value => [
            'Broadcasting/',
            'Events/',
            'Channels/',
        ],
        ModuleContext::SCHEDULE->value => [
            'Console/Commands/',
            'ScheduleServiceProvider.php',
        ],
    ];

    public function __construct(
        private Filesystem $files,
        private LoggerInterface $logger
    ) {}

    /**
     * Analyze a module to determine its supported contexts
     */
    public function analyzeModule(ModuleInfo $module): array
    {
        $contexts = [];

        foreach (self::CONTEXT_INDICATORS as $context => $indicators) {
            if ($this->moduleSupportsContext($module, $indicators)) {
                $contexts[] = $context;
            }
        }

        // Default contexts if none detected
        if (empty($contexts)) {
            $contexts = [ModuleContext::API->value, ModuleContext::WEB->value];
        }

        $this->logger->debug("Module {$module->name} supports contexts: " . implode(', ', $contexts));

        return $contexts;
    }

    /**
     * Analyze multiple modules and build context map
     */
    public function analyzeModules(Collection $modules): array
    {
        $contextMap = [];

        foreach (ModuleContext::all() as $context) {
            $contextMap[$context] = [];
        }

        foreach ($modules as $module) {
            $moduleContexts = $this->analyzeModule($module);

            foreach ($moduleContexts as $context) {
                $contextMap[$context][] = $module->name;
            }
        }

        return $contextMap;
    }

    /**
     * Get optimal loading strategy for current context
     */
    public function getLoadingStrategy(array $currentContexts): array
    {
        $strategy = [
            'eager_modules' => [],
            'lazy_modules' => [],
            'deferred_modules' => [],
            'priority_order' => [],
        ];

        // Determine strategy based on current contexts
        $primaryContext = $this->getPrimaryContext($currentContexts);

        switch ($primaryContext) {
            case ModuleContext::CLI->value:
            case ModuleContext::TESTING->value:
                $strategy['eager_modules'] = $this->getAllModulesForContexts($currentContexts);
                break;

            case ModuleContext::API->value:
                $strategy['lazy_modules'] = $this->getModulesForContext(ModuleContext::API->value);
                $strategy['deferred_modules'] = $this->getModulesForContext(ModuleContext::WEB->value);
                break;

            case ModuleContext::WEB->value:
                $strategy['lazy_modules'] = $this->getModulesForContext(ModuleContext::WEB->value);
                $strategy['deferred_modules'] = $this->getModulesForContext(ModuleContext::API->value);
                break;

            case ModuleContext::ADMIN->value:
                $strategy['eager_modules'] = array_merge(
                    $this->getModulesForContext(ModuleContext::ADMIN->value),
                    $this->getModulesForContext(ModuleContext::WEB->value)
                );
                break;

            default:
                $strategy['lazy_modules'] = $this->getAllModulesForContexts($currentContexts);
        }

        $strategy['priority_order'] = $this->calculatePriorityOrder($currentContexts);

        return $strategy;
    }

    /**
     * Get memory-optimized loading configuration
     */
    public function getMemoryOptimizedConfig(array $currentContexts): array
    {
        $primaryContext = ModuleContext::from($this->getPrimaryContext($currentContexts));
        $constraints = $primaryContext->getMemoryConstraints();

        return [
            'max_memory' => $constraints['max_memory'],
            'gc_threshold' => $constraints['gc_threshold'],
            'lazy_loading_enabled' => $primaryContext->supportsLazyLoading(),
            'eager_loading_required' => $primaryContext->requiresEagerLoading(),
            'parallel_loading' => $this->shouldUseParallelLoading($primaryContext),
            'chunk_size' => $this->getOptimalChunkSize($primaryContext),
        ];
    }

    private function moduleSupportsContext(ModuleInfo $module, array $indicators): bool
    {
        foreach ($indicators as $indicator) {
            $path = $module->path . '/' . $indicator;

            if ($this->files->exists($path)) {
                return true;
            }

            // Check for wildcard patterns
            if (str_contains($indicator, '/') && $this->files->isDirectory(dirname($path))) {
                $pattern = basename($indicator);
                $directory = dirname($path);

                $files = $this->files->glob($directory . '/' . $pattern);
                if (!empty($files)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getPrimaryContext(array $contexts): string
    {
        if (empty($contexts)) {
            return ModuleContext::WEB->value;
        }

        // Sort by priority (lower number = higher priority)
        usort($contexts, function ($a, $b) {
            $contextA = ModuleContext::from($a);
            $contextB = ModuleContext::from($b);
            return $contextA->getPriority() <=> $contextB->getPriority();
        });

        return $contexts[0];
    }

    private function getAllModulesForContexts(array $contexts): array
    {
        $modules = [];

        foreach ($contexts as $context) {
            $modules = array_merge($modules, $this->getModulesForContext($context));
        }

        return array_unique($modules);
    }

    private function getModulesForContext(string $context): array
    {
        // This would be populated from the compiled context map
        // For now, return empty array as this is called during compilation
        return [];
    }

    private function calculatePriorityOrder(array $contexts): array
    {
        return array_map(function ($context) {
            return [
                'context' => $context,
                'priority' => ModuleContext::from($context)->getPriority(),
            ];
        }, $contexts);
    }

    private function shouldUseParallelLoading(ModuleContext $context): bool
    {
        return match ($context) {
            ModuleContext::CLI, ModuleContext::TESTING => true,
            ModuleContext::QUEUE => true,
            default => false,
        };
    }

    private function getOptimalChunkSize(ModuleContext $context): int
    {
        return match ($context) {
            ModuleContext::CLI, ModuleContext::TESTING => 10,
            ModuleContext::API => 3,
            ModuleContext::WEB => 5,
            ModuleContext::ADMIN => 5,
            ModuleContext::QUEUE => 15,
            default => 3,
        };
    }
}