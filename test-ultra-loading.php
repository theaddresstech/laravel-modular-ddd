<?php

require_once __DIR__ . '/vendor/autoload.php';

use TaiCrm\LaravelModularDdd\Context\ModuleContext;
use TaiCrm\LaravelModularDdd\Context\ContextAnalyzer;
use Illuminate\Filesystem\Filesystem;
use Psr\Log\NullLogger;

echo "🚀 Ultra-Module-Loading System Test\n";
echo "==================================\n\n";

// Test ModuleContext detection
echo "📍 Context Detection Test:\n";
echo "Available contexts: " . implode(', ', ModuleContext::all()) . "\n";

// Simulate different contexts
$_SERVER['argv'] = ['artisan', 'test'];
echo "CLI context detected: " . json_encode(ModuleContext::detect()) . "\n";

unset($_SERVER['argv']);
echo "Web context detected: " . json_encode(ModuleContext::detect()) . "\n";

// Test context priorities and constraints
echo "\n🎯 Context Analysis:\n";
foreach (ModuleContext::cases() as $context) {
    $constraints = $context->getMemoryConstraints();
    echo sprintf(
        "%-12s | Priority: %d | Memory: %s | Eager: %s | Lazy: %s\n",
        $context->value,
        $context->getPriority(),
        $constraints['max_memory'],
        $context->requiresEagerLoading() ? 'Yes' : 'No',
        $context->supportsLazyLoading() ? 'Yes' : 'No'
    );
}

// Test ContextAnalyzer with mock module
echo "\n🔍 Context Analyzer Test:\n";
$files = new Filesystem();
$logger = new NullLogger();
$analyzer = new ContextAnalyzer($files, $logger);

// Create a mock module info
$mockModule = new \TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo(
    name: 'TestModule',
    path: __DIR__ . '/test-module',
    version: '1.0.0',
    dependencies: [],
    enabled: true,
    installed: true
);

// Create test module structure
$testModulePath = __DIR__ . '/test-module';
if (!$files->exists($testModulePath)) {
    $files->makeDirectory($testModulePath, 0755, true);
    $files->makeDirectory($testModulePath . '/Http/Controllers/Api', 0755, true);
    $files->makeDirectory($testModulePath . '/routes', 0755, true);
    $files->put($testModulePath . '/routes/api.php', '<?php // API routes');
    $files->put($testModulePath . '/Http/Controllers/Api/TestController.php', '<?php // API controller');
}

try {
    $detectedContexts = $analyzer->analyzeModule($mockModule);
    echo "Mock module contexts: " . implode(', ', $detectedContexts) . "\n";

    $strategy = $analyzer->getLoadingStrategy(['api']);
    echo "API loading strategy: " . json_encode(array_keys($strategy)) . "\n";

    $memoryConfig = $analyzer->getMemoryOptimizedConfig(['api']);
    echo "API memory config: " . json_encode($memoryConfig) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Clean up test files
if ($files->exists($testModulePath)) {
    $files->deleteDirectory($testModulePath);
}

echo "\n✅ Ultra-Module-Loading System Test Complete!\n";