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
                            {--force : Overwrite existing module}';

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
            'display_name' => Str::title($moduleName),
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
                'events' => [],
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
        $replacements = $this->getReplacements($moduleName, $aggregate);

        $stubs = [
            'aggregate.stub' => "Domain/Models/{$aggregate}.php",
            'value-object-id.stub' => "Domain/ValueObjects/{$aggregate}Id.php",
            'repository-interface.stub' => "Domain/Repositories/{$aggregate}RepositoryInterface.php",
            'domain-service.stub' => "Domain/Services/{$aggregate}Service.php",
            'eloquent-repository.stub' => "Infrastructure/Persistence/Eloquent/Repositories/Eloquent{$aggregate}Repository.php",
            'service-provider.stub' => "Providers/{$moduleName}ServiceProvider.php",
            'controller.stub' => "Presentation/Http/Controllers/{$aggregate}Controller.php",
            'routes-api.stub' => "Routes/api.php",
            'routes-web.stub' => "Routes/web.php",
        ];

        foreach ($stubs as $stub => $target) {
            $this->createFileFromStub($stub, $modulePath . '/' . $target, $replacements);
        }
    }

    private function createFileFromStub(string $stub, string $target, array $replacements): void
    {
        $stubPath = $this->stubPath . '/' . $stub;

        if (!$this->files->exists($stubPath)) {
            // Create a basic file if stub doesn't exist
            $this->createBasicFile($target, $replacements);
            return;
        }

        $content = $this->files->get($stubPath);

        foreach ($replacements as $search => $replace) {
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
        $relativePath = str_replace($this->modulesPath . '/' . $module . '/', '', $path);
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
        return [
            '{{MODULE}}' => $moduleName,
            '{{MODULE_SNAKE}}' => Str::snake($moduleName),
            '{{MODULE_KEBAB}}' => Str::kebab($moduleName),
            '{{MODULE_LOWER}}' => Str::lower($moduleName),
            '{{AGGREGATE}}' => Str::studly($aggregate),
            '{{AGGREGATE_SNAKE}}' => Str::snake($aggregate),
            '{{AGGREGATE_KEBAB}}' => Str::kebab($aggregate),
            '{{AGGREGATE_LOWER}}' => Str::lower($aggregate),
            '{{NAMESPACE}}' => "Modules\\{$moduleName}",
        ];
    }

    private function displayNextSteps(string $moduleName): void
    {
        $this->newLine();
        $this->line("📋 <comment>Next steps:</comment>");
        $this->line("1. Review and update the module manifest: modules/{$moduleName}/manifest.json");
        $this->line("2. Implement your domain logic in the Domain layer");
        $this->line("3. Create your database migrations: <info>php artisan make:migration create_{$moduleName}_table --path=modules/{$moduleName}/Database/Migrations</info>");
        $this->line("4. Install the module: <info>php artisan module:install {$moduleName}</info>");
        $this->line("5. Enable the module: <info>php artisan module:enable {$moduleName}</info>");
        $this->newLine();
    }
}