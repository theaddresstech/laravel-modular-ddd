<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ModuleMakeCommand extends Command
{
    protected $signature = 'module:make
                            {name : The name of the module}
                            {--aggregate= : The main aggregate name}
                            {--author= : Module author}
                            {--description= : Module description}
                            {--force : Overwrite existing module}
                            {--no-migration : Skip migration generation}';

    protected $description = 'Create a new DDD module with complete structure';

    public function __construct(
        private Filesystem $files,
        private string $modulesPath,
        private string $stubPath
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $moduleName = Str::studly($this->argument('name'));
        $modulePath = $this->modulesPath . '/' . $moduleName;

        if ($this->files->exists($modulePath) && !$this->option('force')) {
            $this->error("Module '{$moduleName}' already exists. Use --force to overwrite.");
            return self::FAILURE;
        }

        $this->info("Creating module: {$moduleName}");

        try {
            $this->createModuleStructure($moduleName, $modulePath);
            $this->createManifest($moduleName, $modulePath);
            $this->createStubFiles($moduleName, $modulePath);

            // Auto-generate migration unless explicitly disabled
            if (!$this->option('no-migration')) {
                $this->generateMigration($moduleName);
            }

            $this->info("✅ Module '{$moduleName}' created successfully!");
            $this->displayNextSteps($moduleName);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed to create module: " . $e->getMessage());

            // Clean up partial creation
            if ($this->files->exists($modulePath)) {
                $this->files->deleteDirectory($modulePath);
            }

            return self::FAILURE;
        }
    }

    private function createModuleStructure(string $moduleName, string $modulePath): void
    {
        $directories = [
            'Domain/Models',
            'Domain/ValueObjects',
            'Domain/Events',
            'Domain/Services',
            'Domain/Repositories',
            'Domain/Specifications',
            'Domain/Exceptions',
            'Application/Commands',
            'Application/Queries',
            'Application/DTOs',
            'Application/Services',
            'Application/Listeners',
            'Infrastructure/Persistence/Eloquent/Models',
            'Infrastructure/Persistence/Eloquent/Repositories',
            'Infrastructure/External',
            'Infrastructure/Cache',
            'Infrastructure/Events',
            'Presentation/Http/Controllers',
            'Presentation/Http/Requests',
            'Presentation/Http/Resources',
            'Presentation/Console',
            'Database/Migrations',
            'Database/Seeders',
            'Database/Factories',
            'Routes',
            'Resources/views',
            'Resources/assets',
            'Resources/lang',
            'Tests/Unit',
            'Tests/Feature',
            'Tests/Integration',
            'Config',
            'Providers',
        ];

        foreach ($directories as $directory) {
            $fullPath = $modulePath . '/' . $directory;
            $this->files->makeDirectory($fullPath, 0755, true);

            // Create .gitkeep for empty directories
            $this->files->put($fullPath . '/.gitkeep', '');
        }
    }

    private function createManifest(string $moduleName, string $modulePath): void
    {
        $aggregate = $this->option('aggregate') ?? $moduleName;
        $author = $this->option('author') ?? config('app.name', 'Unknown');
        $description = $this->option('description') ?? "The {$moduleName} module";

        $manifest = [
            'name' => $moduleName,
            'display_name' => $this->formatDisplayName($moduleName),
            'description' => $description,
            'version' => '1.0.0',
            'author' => $author,
            'dependencies' => [],
            'optional_dependencies' => [],
            'conflicts' => [],
            'provides' => [
                'services' => [
                    Str::studly($aggregate) . 'Service',
                ],
                'contracts' => [
                    Str::studly($aggregate) . 'RepositoryInterface',
                ],
                'events' => [
                    Str::studly($aggregate) . 'Created',
                    Str::studly($aggregate) . 'NameChanged',
                ],
                'listeners' => [
                    Str::studly($aggregate) . 'CreatedListener',
                    Str::studly($aggregate) . 'NameChangedListener',
                ],
            ],
            'config' => [
                'auto_load' => true,
                'cache_enabled' => true,
            ],
        ];

        $manifestPath = $modulePath . '/manifest.json';
        $this->files->put(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function createStubFiles(string $moduleName, string $modulePath): void
    {
        $aggregate = $this->option('aggregate') ?? $moduleName;
        $aggregateStudly = Str::studly($aggregate);
        $replacements = $this->getReplacements($moduleName, $aggregate);

        $stubs = [
            'aggregate.stub' => "Domain/Models/{$aggregateStudly}.php",
            'value-object-id.stub' => "Domain/ValueObjects/{$aggregateStudly}Id.php",
            'repository-interface.stub' => "Domain/Repositories/{$aggregateStudly}RepositoryInterface.php",
            'domain-service.stub' => "Domain/Services/{$aggregateStudly}Service.php",
            'eloquent-model.stub' => "Infrastructure/Persistence/Eloquent/Models/{$aggregateStudly}.php",
            'eloquent-repository.stub' => "Infrastructure/Persistence/Eloquent/Repositories/Eloquent{$aggregateStudly}Repository.php",
            'resource.stub' => "Presentation/Http/Resources/{$aggregateStudly}Resource.php",
            'service-provider.stub' => "Providers/{$moduleName}ServiceProvider.php",
            'controller.stub' => "Presentation/Http/Controllers/{$aggregateStudly}Controller.php",
            'factory.stub' => "Database/Factories/{$aggregateStudly}.php",
            // Auto-generated Events
            'aggregate-created-event.stub' => "Domain/Events/{$aggregateStudly}Created.php",
            'aggregate-name-changed-event.stub' => "Domain/Events/{$aggregateStudly}NameChanged.php",
            // Auto-generated Event Listeners
            'aggregate-created-listener.stub' => "Application/Listeners/{$aggregateStudly}CreatedListener.php",
            'aggregate-name-changed-listener.stub' => "Application/Listeners/{$aggregateStudly}NameChangedListener.php",
            'routes-api.stub' => "Routes/api.php",
            'routes-web.stub' => "Routes/web.php",
        ];

        foreach ($stubs as $stub => $target) {
            $this->createFileFromStub($stub, $modulePath . '/' . $target, $replacements, $target);
        }
    }

    private function createFileFromStub(string $stub, string $target, array $replacements, string $relativePath = null): void
    {
        $stubPath = $this->stubPath . '/' . $stub;

        if (!$this->files->exists($stubPath)) {
            // Create a basic file if stub doesn't exist
            $this->createBasicFile($target, $replacements);
            return;
        }

        $content = $this->files->get($stubPath);

        // Get the correct namespace for this specific target file
        $pathForNamespace = $relativePath ?? $target;
        $namespace = $this->getNamespaceFromPath($pathForNamespace, $replacements['{{MODULE}}']);

        // Create enhanced replacements with specific namespace for the target file
        // Keep {{NAMESPACE_MODULE}} for use statements, use {{NAMESPACE}} for file namespace
        $enhancedReplacements = array_merge($replacements, [
            '{{NAMESPACE}}' => $namespace,
            '{{CLASS_NAME}}' => basename($target, '.php'),
        ]);

        // Use a more specific replacement pattern to avoid double replacements
        // Sort replacements by key length (longest first) to avoid partial replacements
        uksort($enhancedReplacements, function($a, $b) {
            return strlen($b) - strlen($a);
        });

        foreach ($enhancedReplacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        $targetDir = dirname($target);
        if (!$this->files->exists($targetDir)) {
            $this->files->makeDirectory($targetDir, 0755, true);
        }

        $this->files->put($target, $content);
    }

    private function createBasicFile(string $target, array $replacements): void
    {
        $namespace = $this->getNamespaceFromPath($target, $replacements['{{MODULE}}']);
        $className = basename($target, '.php');

        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nclass {$className}\n{\n    // TODO: Implement\n}\n";

        $targetDir = dirname($target);
        if (!$this->files->exists($targetDir)) {
            $this->files->makeDirectory($targetDir, 0755, true);
        }

        $this->files->put($target, $content);
    }

    private function getNamespaceFromPath(string $path, string $module): string
    {
        // If path contains the full modules path, strip it
        if (str_contains($path, $this->modulesPath)) {
            $relativePath = str_replace($this->modulesPath . '/' . $module . '/', '', $path);
        } else {
            // Path is already relative to module root
            $relativePath = $path;
        }

        $directory = dirname($relativePath);

        if ($directory === '.') {
            return "Modules\\{$module}";
        }

        $namespaceParts = explode('/', $directory);
        $namespace = 'Modules\\' . $module . '\\' . implode('\\', $namespaceParts);

        return $namespace;
    }

    private function getReplacements(string $moduleName, string $aggregate): array
    {
        $aggregateStudly = Str::studly($aggregate);

        return [
            '{{NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{NAMESPACE_MODULE}}' => "Modules\\{$moduleName}",
            '{{MODULE}}' => $moduleName,
            '{{MODULE_SNAKE}}' => Str::snake($moduleName),
            '{{MODULE_KEBAB}}' => Str::kebab($moduleName),
            '{{MODULE_LOWER}}' => Str::lower($moduleName),
            '{{AGGREGATE}}' => $aggregateStudly,
            '{{AGGREGATE_SNAKE}}' => Str::snake($aggregate),
            '{{AGGREGATE_KEBAB}}' => Str::kebab($aggregate),
            '{{AGGREGATE_LOWER}}' => Str::camel($aggregate),
            '{{AGGREGATE_VARIABLE}}' => Str::camel($aggregate),
            '{{CLASS_NAME}}' => $aggregateStudly,
            '{{class}}' => $aggregateStudly,
            '{{AGGREGATE_SERVICE}}' => $aggregateStudly . 'Service',
            '{{MODEL}}' => $aggregateStudly,
            '{{FACTORY}}' => $aggregateStudly,
            '{{MODEL_VARIABLE}}' => Str::camel($aggregate),
            '{{EVENT}}' => $aggregateStudly,
        ];
    }

    private function formatDisplayName(string $moduleName): string
    {
        // Handle common patterns for better display names
        $formatted = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1 $2', $moduleName);
        $formatted = preg_replace('/([a-z\d])([A-Z])/', '$1 $2', $formatted);

        // Special cases for common abbreviations
        $abbreviations = ['HR', 'API', 'CRM', 'SMS', 'PDF', 'CSV', 'XML', 'JSON', 'URL', 'UUID'];
        foreach ($abbreviations as $abbr) {
            $formatted = str_ireplace($abbr, $abbr, $formatted);
        }

        return trim($formatted);
    }

    private function displayNextSteps(string $moduleName): void
    {
        $aggregate = $this->option('aggregate') ?? $moduleName;
        $aggregateStudly = Str::studly($aggregate);

        $this->newLine();
        $this->line("📋 <comment>Next steps:</comment>");
        $this->line("1. Review and update the module manifest: modules/{$moduleName}/manifest.json");
        $this->line("2. Implement your domain logic in the Domain layer");
        $this->line("3. Customize the auto-generated event listeners:");
        $this->line("   - Application/Listeners/{$aggregateStudly}CreatedListener.php");
        $this->line("   - Application/Listeners/{$aggregateStudly}NameChangedListener.php");
        $this->line("4. Review and customize the auto-generated migration: modules/{$moduleName}/Database/Migrations/");
        $this->line("5. Install the module: <info>php artisan module:install {$moduleName}</info>");
        $this->line("6. Enable the module: <info>php artisan module:enable {$moduleName}</info>");
        $this->newLine();
        $this->line("🎉 <info>Auto-generated components:</info>");
        $this->line("   ✅ Domain Events: {$aggregateStudly}Created, {$aggregateStudly}NameChanged");
        $this->line("   ✅ Event Listeners: Automatically wired and ready for customization");
        $this->line("   ✅ Database Migration: Basic table structure with UUID primary key");
        $this->line("   ✅ Complete DDD structure with timestamps and event handling");
        $this->newLine();
    }

    private function generateMigration(string $moduleName): void
    {
        $aggregate = $this->option('aggregate') ?? $moduleName;
        $tableName = Str::snake(Str::plural($aggregate));

        $this->info("🗄️  Generating migration for {$tableName} table...");

        $this->call('module:make-migration', [
            'module' => $moduleName,
            'name' => "create_{$tableName}_table",
            '--create' => $tableName
        ]);
    }
}