<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ModuleStubCommand extends Command
{
    protected $signature = 'module:stub
                            {type : Type of stub to generate (model|controller|service|repository|event|command|query)}
                            {name : Name of the class to generate}
                            {module : Target module name}
                            {--force : Overwrite existing files}';

    protected $description = 'Generate DDD components for a module';

    public function __construct(
        private ModuleManagerInterface $moduleManager,
        private Filesystem $files,
        private string $stubPath
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $type = $this->argument('type');
        $name = $this->argument('name');
        $moduleName = $this->argument('module');

        try {
            $module = $this->moduleManager->getInfo($moduleName);

            if (!$module) {
                $this->error("Module '{$moduleName}' not found");
                return self::FAILURE;
            }

            $this->info("🔨 Generating {$type}: {$name} for module {$moduleName}");

            $result = $this->generateStub($type, $name, $module);

            if ($result['success']) {
                $this->info("✅ {$result['type']} created: {$result['path']}");

                if (!empty($result['next_steps'])) {
                    $this->newLine();
                    $this->line('📋 Next steps:');
                    foreach ($result['next_steps'] as $step) {
                        $this->line("   • {$step}");
                    }
                }

                return self::SUCCESS;
            } else {
                $this->error("❌ Failed to create {$type}: {$result['error']}");
                return self::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error("❌ Generation failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function generateStub(string $type, string $name, $module): array
    {
        $generators = [
            'model' => fn() => $this->generateModel($name, $module),
            'controller' => fn() => $this->generateController($name, $module),
            'service' => fn() => $this->generateService($name, $module),
            'repository' => fn() => $this->generateRepository($name, $module),
            'event' => fn() => $this->generateEvent($name, $module),
            'command' => fn() => $this->generateCommand($name, $module),
            'query' => fn() => $this->generateQuery($name, $module),
        ];

        if (!isset($generators[$type])) {
            return [
                'success' => false,
                'error' => "Unknown stub type: {$type}. Available types: " . implode(', ', array_keys($generators)),
            ];
        }

        return $generators[$type]();
    }

    private function generateModel(string $name, $module): array
    {
        $className = Str::studly($name);
        $path = "{$module->path}/Domain/Models/{$className}.php";

        if ($this->files->exists($path) && !$this->option('force')) {
            return ['success' => false, 'error' => 'Model already exists. Use --force to overwrite.'];
        }

        $content = $this->processStub('model.stub', [
            'CLASS_NAME' => $className,
            'MODULE' => $module->name,
            'NAMESPACE' => "Modules\\{$module->name}\\Domain\\Models",
        ]);

        $this->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);

        return [
            'success' => true,
            'type' => 'Model',
            'path' => $path,
            'next_steps' => [
                "Create the corresponding value object: php artisan module:stub value-object {$className}Id {$module->name}",
                "Create the repository interface: php artisan module:stub repository {$className} {$module->name}",
                "Add the model properties and business logic",
            ],
        ];
    }

    private function generateController(string $name, $module): array
    {
        $className = Str::studly($name) . 'Controller';
        $path = "{$module->path}/Presentation/Http/Controllers/{$className}.php";

        if ($this->files->exists($path) && !$this->option('force')) {
            return ['success' => false, 'error' => 'Controller already exists. Use --force to overwrite.'];
        }

        $content = $this->processStub('controller.stub', [
            'CLASS_NAME' => $className,
            'MODULE' => $module->name,
            'NAMESPACE' => "Modules\\{$module->name}\\Presentation\\Http\\Controllers",
            'RESOURCE_NAME' => Str::studly($name),
            'RESOURCE_LOWER' => Str::lower($name),
        ]);

        $this->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);

        return [
            'success' => true,
            'type' => 'Controller',
            'path' => $path,
            'next_steps' => [
                "Create request classes: php artisan make:request Store{$name}Request",
                "Create resource classes: php artisan make:resource {$name}Resource",
                "Add routes in {$module->path}/Routes/api.php",
            ],
        ];
    }

    private function generateService(string $name, $module): array
    {
        $className = Str::studly($name) . 'Service';
        $path = "{$module->path}/Domain/Services/{$className}.php";

        if ($this->files->exists($path) && !$this->option('force')) {
            return ['success' => false, 'error' => 'Service already exists. Use --force to overwrite.'];
        }

        $content = $this->processStub('domain-service.stub', [
            'CLASS_NAME' => $className,
            'MODULE' => $module->name,
            'NAMESPACE' => "Modules\\{$module->name}\\Domain\\Services",
            'SERVICE_NAME' => Str::studly($name),
        ]);

        $this->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);

        return [
            'success' => true,
            'type' => 'Domain Service',
            'path' => $path,
            'next_steps' => [
                "Implement the service logic",
                "Register the service in the module's service provider",
                "Write unit tests for the service",
            ],
        ];
    }

    private function generateRepository(string $name, $module): array
    {
        $className = Str::studly($name) . 'RepositoryInterface';
        $path = "{$module->path}/Domain/Repositories/{$className}.php";

        if ($this->files->exists($path) && !$this->option('force')) {
            return ['success' => false, 'error' => 'Repository interface already exists. Use --force to overwrite.'];
        }

        $content = $this->processStub('repository-interface.stub', [
            'CLASS_NAME' => $className,
            'MODULE' => $module->name,
            'NAMESPACE' => "Modules\\{$module->name}\\Domain\\Repositories",
            'AGGREGATE' => Str::studly($name),
            'AGGREGATE_LOWER' => Str::lower($name),
        ]);

        $this->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);

        return [
            'success' => true,
            'type' => 'Repository Interface',
            'path' => $path,
            'next_steps' => [
                "Create the Eloquent implementation: php artisan module:stub eloquent-repository {$name} {$module->name}",
                "Register the binding in the module's service provider",
                "Implement the repository methods",
            ],
        ];
    }

    private function generateEvent(string $name, $module): array
    {
        $className = Str::studly($name);
        $path = "{$module->path}/Domain/Events/{$className}.php";

        if ($this->files->exists($path) && !$this->option('force')) {
            return ['success' => false, 'error' => 'Event already exists. Use --force to overwrite.'];
        }

        $content = $this->processStub('domain-event.stub', [
            'CLASS_NAME' => $className,
            'MODULE' => $module->name,
            'NAMESPACE' => "Modules\\{$module->name}\\Domain\\Events",
            'EVENT_NAME' => $className,
        ]);

        $this->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);

        return [
            'success' => true,
            'type' => 'Domain Event',
            'path' => $path,
            'next_steps' => [
                "Implement the event payload",
                "Create event listeners if needed",
                "Trigger the event in your aggregate root or domain service",
            ],
        ];
    }

    private function generateCommand(string $name, $module): array
    {
        $className = Str::studly($name) . 'Command';
        $handlerName = Str::studly($name) . 'Handler';
        $commandPath = "{$module->path}/Application/Commands/{$className}.php";
        $handlerPath = "{$module->path}/Application/Commands/{$handlerName}.php";

        if (($this->files->exists($commandPath) || $this->files->exists($handlerPath)) && !$this->option('force')) {
            return ['success' => false, 'error' => 'Command or handler already exists. Use --force to overwrite.'];
        }

        // Generate command
        $commandContent = $this->processStub('command.stub', [
            'CLASS_NAME' => $className,
            'MODULE' => $module->name,
            'NAMESPACE' => "Modules\\{$module->name}\\Application\\Commands",
        ]);

        // Generate handler
        $handlerContent = $this->processStub('command-handler.stub', [
            'CLASS_NAME' => $handlerName,
            'COMMAND_CLASS' => $className,
            'MODULE' => $module->name,
            'NAMESPACE' => "Modules\\{$module->name}\\Application\\Commands",
        ]);

        $this->ensureDirectoryExists(dirname($commandPath));
        $this->files->put($commandPath, $commandContent);
        $this->files->put($handlerPath, $handlerContent);

        return [
            'success' => true,
            'type' => 'Command + Handler',
            'path' => dirname($commandPath),
            'next_steps' => [
                "Implement the command properties and validation",
                "Implement the handler logic",
                "Register the command handler in your command bus",
            ],
        ];
    }

    private function generateQuery(string $name, $module): array
    {
        $className = Str::studly($name) . 'Query';
        $handlerName = Str::studly($name) . 'Handler';
        $queryPath = "{$module->path}/Application/Queries/{$className}.php";
        $handlerPath = "{$module->path}/Application/Queries/{$handlerName}.php";

        if (($this->files->exists($queryPath) || $this->files->exists($handlerPath)) && !$this->option('force')) {
            return ['success' => false, 'error' => 'Query or handler already exists. Use --force to overwrite.'];
        }

        // Generate query
        $queryContent = $this->processStub('query.stub', [
            'CLASS_NAME' => $className,
            'MODULE' => $module->name,
            'NAMESPACE' => "Modules\\{$module->name}\\Application\\Queries",
        ]);

        // Generate handler
        $handlerContent = $this->processStub('query-handler.stub', [
            'CLASS_NAME' => $handlerName,
            'QUERY_CLASS' => $className,
            'MODULE' => $module->name,
            'NAMESPACE' => "Modules\\{$module->name}\\Application\\Queries",
        ]);

        $this->ensureDirectoryExists(dirname($queryPath));
        $this->files->put($queryPath, $queryContent);
        $this->files->put($handlerPath, $handlerContent);

        return [
            'success' => true,
            'type' => 'Query + Handler',
            'path' => dirname($queryPath),
            'next_steps' => [
                "Implement the query parameters and validation",
                "Implement the query handler logic",
                "Register the query handler in your query bus",
            ],
        ];
    }

    private function processStub(string $stubName, array $replacements): string
    {
        $stubPath = $this->stubPath . '/' . $stubName;

        if (!$this->files->exists($stubPath)) {
            // Create basic stub if doesn't exist
            return $this->createBasicStub($replacements);
        }

        $content = $this->files->get($stubPath);

        foreach ($replacements as $placeholder => $value) {
            $content = str_replace("{{$placeholder}}", $value, $content);
        }

        return $content;
    }

    private function createBasicStub(array $replacements): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$replacements['NAMESPACE']};\n\nclass {$replacements['CLASS_NAME']}\n{\n    // TODO: Implement\n}\n";
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!$this->files->exists($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }
    }
}