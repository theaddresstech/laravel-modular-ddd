<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ModuleMakeTestCommand extends Command
{
    protected $signature = 'module:make-test
                            {module : The name of the module}
                            {name : The name of the test}
                            {--unit : Create a unit test}
                            {--feature : Create a feature test}
                            {--integration : Create an integration test}
                            {--class= : The class being tested}
                            {--force : Overwrite existing test}';
    protected $description = 'Create a new test for a module';

    public function __construct(
        private Filesystem $files,
        private string $modulesPath,
        private string $stubPath,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $moduleName = Str::studly($this->argument('module'));
        $testName = Str::studly($this->argument('name'));
        $className = $this->option('class');

        $modulePath = $this->modulesPath . '/' . $moduleName;

        if (!$this->files->exists($modulePath)) {
            $this->error("Module '{$moduleName}' does not exist. Create it first using module:make");

            return self::FAILURE;
        }

        $testType = $this->determineTestType();
        $testPath = $this->getTestPath($modulePath, $testName, $testType);

        if ($this->files->exists($testPath) && !$this->option('force')) {
            $this->error("Test '{$testName}' already exists in module '{$moduleName}'. Use --force to overwrite.");

            return self::FAILURE;
        }

        try {
            $this->createTest($testPath, $moduleName, $testName, $testType, $className);
            $this->info("✅ {$testType} test '{$testName}' created successfully in module '{$moduleName}'!");
            $this->displayNextSteps($moduleName, $testName, $testType);

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('❌ Failed to create test: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function determineTestType(): string
    {
        if ($this->option('unit')) {
            return 'Unit';
        }

        if ($this->option('feature')) {
            return 'Feature';
        }

        if ($this->option('integration')) {
            return 'Integration';
        }

        // Default to unit test
        return 'Unit';
    }

    private function getTestPath(string $modulePath, string $testName, string $testType): string
    {
        return $modulePath . '/Tests/' . $testType . '/' . $testName . '.php';
    }

    private function createTest(string $testPath, string $moduleName, string $testName, string $testType, ?string $className): void
    {
        $stubFile = match ($testType) {
            'Unit' => 'unit-test.stub',
            'Feature' => 'feature-test.stub',
            'Integration' => 'integration-test.stub',
            default => 'unit-test.stub'
        };

        $stubPath = $this->stubPath . '/' . $stubFile;

        if (!$this->files->exists($stubPath)) {
            $this->createTestFromTemplate($testPath, $moduleName, $testName, $testType, $className);

            return;
        }

        $content = $this->files->get($stubPath);
        $replacements = $this->getReplacements($moduleName, $testName, $testType, $className);

        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        $testDir = dirname($testPath);
        if (!$this->files->exists($testDir)) {
            $this->files->makeDirectory($testDir, 0o755, true);
        }

        $this->files->put($testPath, $content);
    }

    private function createTestFromTemplate(string $testPath, string $moduleName, string $testName, string $testType, ?string $className): void
    {
        $namespace = "Modules\\{$moduleName}\\Tests\\{$testType}";
        $baseTestClass = $this->getBaseTestClass($testType);
        $testMethods = $this->generateTestMethods($testName, $className, $testType);

        $content = "<?php

declare(strict_types=1);

namespace {$namespace};

use {$baseTestClass};
" . ($className ? "use Modules\\{$moduleName}\\Domain\\Models\\{$className};\n" : '') . "
class {$testName} extends " . class_basename($baseTestClass) . "
{
{$testMethods}
}
";

        $testDir = dirname($testPath);
        if (!$this->files->exists($testDir)) {
            $this->files->makeDirectory($testDir, 0o755, true);
        }

        $this->files->put($testPath, $content);
    }

    private function getBaseTestClass(string $testType): string
    {
        return match ($testType) {
            'Unit' => 'PHPUnit\\Framework\\TestCase',
            'Feature' => 'Tests\\TestCase',
            'Integration' => 'Tests\\TestCase',
            default => 'PHPUnit\\Framework\\TestCase'
        };
    }

    private function generateTestMethods(string $testName, ?string $className, string $testType): string
    {
        if ($testType === 'Unit' && $className) {
            return $this->generateUnitTestMethods($className);
        }

        if ($testType === 'Feature') {
            return $this->generateFeatureTestMethods($testName);
        }

        if ($testType === 'Integration') {
            return $this->generateIntegrationTestMethods($testName);
        }

        return '    public function test_example(): void
    {
        $this->assertTrue(true);
    }';
    }

    private function generateUnitTestMethods(string $className): string
    {
        $variable = Str::camel($className);

        return "    public function test_can_create_{$variable}(): void
    {
        // Arrange
        \$id = {$className}Id::generate();
        \$name = 'Test Name';

        // Act
        \${$variable} = {$className}::create(\$id, \$name);

        // Assert
        \$this->assertInstanceOf({$className}::class, \${$variable});
        \$this->assertEquals(\$id, \${$variable}->getId());
        \$this->assertEquals(\$name, \${$variable}->getName());
    }

    public function test_can_change_{$variable}_name(): void
    {
        // Arrange
        \${$variable} = {$className}::create(
            {$className}Id::generate(),
            'Original Name'
        );
        \$newName = 'New Name';

        // Act
        \${$variable}->changeName(\$newName);

        // Assert
        \$this->assertEquals(\$newName, \${$variable}->getName());
    }

    public function test_does_not_change_name_if_same(): void
    {
        // Arrange
        \$name = 'Same Name';
        \${$variable} = {$className}::create(
            {$className}Id::generate(),
            \$name
        );

        // Act
        \${$variable}->changeName(\$name);

        // Assert
        \$this->assertEquals(\$name, \${$variable}->getName());
    }";
    }

    private function generateFeatureTestMethods(string $testName): string
    {
        return "    public function test_feature_example(): void
    {
        // Test a complete user journey or feature
        \$response = \$this->get('/');

        \$response->assertStatus(200);
    }

    public function test_authenticated_user_can_access_feature(): void
    {
        // \$user = User::factory()->create();
        // \$this->actingAs(\$user);

        // Test authenticated behavior
        \$this->assertTrue(true);
    }";
    }

    private function generateIntegrationTestMethods(string $testName): string
    {
        return '    public function test_integration_between_components(): void
    {
        // Test integration between different parts of the system
        $this->assertTrue(true);
    }

    public function test_external_service_integration(): void
    {
        // Test integration with external services
        $this->assertTrue(true);
    }';
    }

    private function getReplacements(string $moduleName, string $testName, string $testType, ?string $className): array
    {
        return [
            '{{MODULE}}' => $moduleName,
            '{{TEST}}' => $testName,
            '{{TEST_TYPE}}' => $testType,
            '{{CLASS}}' => $className ?: 'ExampleClass',
            '{{CLASS_VARIABLE}}' => $className ? Str::camel($className) : 'exampleClass',
            '{{NAMESPACE}}' => "Modules\\{$moduleName}",
        ];
    }

    private function displayNextSteps(string $moduleName, string $testName, string $testType): void
    {
        $this->newLine();
        $this->line('📋 <comment>Next steps:</comment>');
        $this->line("1. Implement test logic in: modules/{$moduleName}/Tests/{$testType}/{$testName}.php");
        $this->line("2. Run the test: <info>php artisan test modules/{$moduleName}/Tests/{$testType}/{$testName}.php</info>");

        if ($testType === 'Unit') {
            $this->line("3. Consider creating a factory: <info>php artisan module:make-factory {$moduleName} YourModelFactory</info>");
        }

        if ($testType === 'Feature') {
            $this->line('3. Add test database setup and user authentication as needed');
        }

        $this->line('4. Add more test methods to cover different scenarios');
        $this->newLine();
    }
}
