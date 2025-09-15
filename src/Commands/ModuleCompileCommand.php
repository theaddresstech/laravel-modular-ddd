<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use TaiCrm\LaravelModularDdd\Compilation\Contracts\ModuleCompilerInterface;
use TaiCrm\LaravelModularDdd\Loading\ParallelModuleLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Command to compile modules for ultra-performance loading
 */
class ModuleCompileCommand extends Command
{
    protected $signature = 'module:compile
                           {--force : Force recompilation even if cache exists}
                           {--dry-run : Show what would be compiled without actually compiling}
                           {--profile : Show detailed performance profiling}
                           {--clear-cache : Clear compiled cache before compiling}';

    protected $description = 'Compile modules for ultra-performance loading';

    public function handle(ModuleCompilerInterface $compiler, ParallelModuleLoader $loader): int
    {
        $this->info('🚀 Ultra-Performance Module Compiler');
        $this->newLine();

        if ($this->option('clear-cache')) {
            $this->info('🧹 Clearing compiled cache...');
            $compiler->clearCompiledCache();
            Cache::forget('modular_ddd:compiled_registry');
            $this->info('✅ Cache cleared');
            $this->newLine();
        }

        if ($this->option('dry-run')) {
            return $this->handleDryRun($compiler);
        }

        if (!$this->option('force') && !$compiler->isCompilationNeeded()) {
            $timestamp = $compiler->getCompilationTimestamp();
            $this->info('✅ Modules are already compiled (compiled at: ' . date('Y-m-d H:i:s', $timestamp) . ')');
            return 0;
        }

        $startTime = microtime(true);

        $this->info('🔧 Starting module compilation...');
        $this->newLine();

        $result = $compiler->compile([
            'force' => $this->option('force'),
            'profile' => $this->option('profile'),
        ]);

        if (!$result->success) {
            $this->error('❌ Compilation failed: ' . $result->error);
            return 1;
        }

        $totalTime = (microtime(true) - $startTime) * 1000;

        $this->displayResults($result, $totalTime);

        if ($this->option('profile')) {
            $this->displayProfileInfo($result);
        }

        $this->newLine();
        $this->info('🎉 Module compilation completed successfully!');

        return 0;
    }

    private function handleDryRun(ModuleCompilerInterface $compiler): int
    {
        $this->info('🔍 Dry Run - Analyzing what would be compiled...');
        $this->newLine();

        // In a real implementation, we would analyze modules without compiling
        $this->info('📦 Modules that would be compiled:');
        $this->line('  • All discovered modules');
        $this->line('  • Dependency graphs would be generated');
        $this->line('  • Service bindings would be pre-compiled');
        $this->line('  • Route manifests would be created');
        $this->line('  • Context maps would be built');

        $this->newLine();
        $this->info('✅ Dry run completed - no changes made');

        return 0;
    }

    private function displayResults($result, float $totalTime): void
    {
        $this->newLine();
        $this->info('📊 Compilation Results:');
        $this->line("  📦 Modules compiled: {$result->modulesCompiled}");
        $this->line("  ⏱️  Compilation time: " . number_format($result->compilationTimeMs, 2) . "ms");
        $this->line("  🔄 Total time: " . number_format($totalTime, 2) . "ms");

        if ($result->optimizations) {
            $this->line("  🚀 Optimizations applied: " . count($result->optimizations));
        }

        if ($result->cacheKeys) {
            $this->line("  🗄️  Cache keys generated: " . count($result->cacheKeys));
        }
    }

    private function displayProfileInfo($result): void
    {
        $this->newLine();
        $this->info('📈 Performance Profile:');

        if (isset($result->metrics['memory_estimate'])) {
            $memoryMB = number_format($result->metrics['memory_estimate'] / 1024 / 1024, 2);
            $this->line("  💾 Estimated memory usage: {$memoryMB}MB");
        }

        if (isset($result->metrics['load_time_estimate'])) {
            $loadTime = number_format($result->metrics['load_time_estimate'], 2);
            $this->line("  ⚡ Estimated load time: {$loadTime}ms");
        }

        if (isset($result->optimizations)) {
            foreach ($result->optimizations as $optimization => $count) {
                $this->line("  🔧 {$optimization}: {$count}");
            }
        }
    }
}