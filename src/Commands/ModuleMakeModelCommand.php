<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ModuleMakeModelCommand extends Command
{
    protected $signature = 'module:make-model
                            {module : The module name}
                            {name : The model name}
                            {--migration : Also create a migration}
                            {--factory : Also create a factory}
                            {--resource : Also create an API resource}
                            {--controller : Also create a controller}
                            {--all : Create migration, factory, resource, and controller}
                            {--force : Overwrite existing files}';

    protected $description = 'Create a new Eloquent model in a module with optional related files';

    public function __construct(
        private Filesystem $files,
        private string $modulesPath,
        private string $stubPath
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $moduleName = Str::studly($this->argument('module'));
        $modelName = Str::studly($this->argument('name'));
        $modulePath = $this->modulesPath . '/' . $moduleName;

        if (!$this->files->exists($modulePath)) {
            $this->error("Module '{$moduleName}' does not exist.");
            return self::FAILURE;
        }

        $this->info("Creating model '{$modelName}' in module '{$moduleName}'");

        try {
            // Create the Eloquent model
            $this->createModel($moduleName, $modelName, $modulePath);

            // Create related files based on options
            if ($this->option('all') || $this->option('migration')) {
                $this->createMigration($moduleName, $modelName);
            }

            if ($this->option('all') || $this->option('factory')) {
                $this->createFactory($moduleName, $modelName);
            }

            if ($this->option('all') || $this->option('resource')) {
                $this->createResource($moduleName, $modelName);
            }

            if ($this->option('all') || $this->option('controller')) {
                $this->createController($moduleName, $modelName);
            }

            $this->info("✅ Model '{$modelName}' created successfully in module '{$moduleName}'!");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed to create model: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function createModel(string $moduleName, string $modelName, string $modulePath): void
    {
        $modelPath = $modulePath . '/Infrastructure/Persistence/Eloquent/Models/' . $modelName . '.php';

        if ($this->files->exists($modelPath) && !$this->option('force')) {
            $this->error("Model '{$modelName}' already exists. Use --force to overwrite.");
            return;
        }

        $replacements = $this->getReplacements($moduleName, $modelName);
        $this->createFileFromStub('eloquent-model.stub', $modelPath, $replacements);

        $this->line("   ✅ Model: Infrastructure/Persistence/Eloquent/Models/{$modelName}.php");
    }

    private function createMigration(string $moduleName, string $modelName): void
    {
        $tableName = Str::snake(Str::plural($modelName));
        $migrationName = "create_{$tableName}_table";

        $this->line("   🗄️  Creating migration for {$tableName} table...");

        $this->call('module:make-migration', [
            'module' => $moduleName,
            'name' => $migrationName,
            '--create' => $tableName
        ]);
    }

    private function createFactory(string $moduleName, string $modelName): void
    {
        $this->line("   🏭 Creating factory...");

        $this->call('module:make-factory', [
            'module' => $moduleName,
            'name' => $modelName
        ]);
    }

    private function createResource(string $moduleName, string $modelName): void
    {
        $resourceName = $modelName . 'Resource';

        $this->line("   📦 Creating API resource...");

        $this->call('module:make-resource', [
            'module' => $moduleName,
            'name' => $resourceName,
            '--model' => $modelName
        ]);
    }

    private function createController(string $moduleName, string $modelName): void
    {
        $controllerName = $modelName . 'Controller';

        $this->line("   🎮 Creating controller...");

        $this->call('module:make-controller', [
            'module' => $moduleName,
            'name' => $controllerName,
            '--api' => true,
            '--resource' => $modelName
        ]);
    }

    private function createFileFromStub(string $stub, string $target, array $replacements): void
    {
        $stubPath = $this->stubPath . '/' . $stub;

        if (!$this->files->exists($stubPath)) {
            $this->createBasicModel($target, $replacements);
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

    private function createBasicModel(string $target, array $replacements): void
    {
        $modelName = $replacements['{{MODEL}}'];
        $namespace = "Modules\\{$replacements['{{MODULE}}']}\\Infrastructure\\Persistence\\Eloquent\\Models";
        $tableName = Str::snake(Str::plural($modelName));

        $content = "<?php

declare(strict_types=1);

namespace {$namespace};

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class {$modelName} extends Model
{
    use HasFactory, HasUuids;

    protected \$table = '{$tableName}';

    protected \$fillable = [
        // Add your fillable fields here
    ];

    protected \$casts = [
        // Add your casts here
    ];

    // Add your relationships and methods here
}
";

        $targetDir = dirname($target);
        if (!$this->files->exists($targetDir)) {
            $this->files->makeDirectory($targetDir, 0755, true);
        }

        $this->files->put($target, $content);
    }

    private function getReplacements(string $moduleName, string $modelName): array
    {
        return [
            '{{MODULE}}' => $moduleName,
            '{{MODEL}}' => $modelName,
            '{{MODEL_VARIABLE}}' => Str::camel($modelName),
            '{{TABLE_NAME}}' => Str::snake(Str::plural($modelName)),
            '{{NAMESPACE_MODULE}}' => "Modules\\{$moduleName}",
        ];
    }
}