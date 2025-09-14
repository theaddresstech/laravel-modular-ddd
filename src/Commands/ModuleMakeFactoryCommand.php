<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ModuleMakeFactoryCommand extends Command
{
    protected $signature = 'module:make-factory
                            {module : The name of the module}
                            {name : The name of the factory}
                            {--model= : The model this factory creates}
                            {--force : Overwrite existing factory}';

    protected $description = 'Create a new factory for a module';

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
        $factoryName = Str::studly($this->argument('name'));
        $modelName = $this->option('model');

        // Auto-detect model name from factory name if not provided
        if (!$modelName && Str::endsWith($factoryName, 'Factory')) {
            $modelName = Str::replaceLast('Factory', '', $factoryName);
        }

        $modulePath = $this->modulesPath . '/' . $moduleName;

        if (!$this->files->exists($modulePath)) {
            $this->error("Module '{$moduleName}' does not exist. Create it first using module:make");
            return self::FAILURE;
        }

        $factoryPath = $modulePath . '/Database/Factories/' . $factoryName . '.php';

        if ($this->files->exists($factoryPath) && !$this->option('force')) {
            $this->error("Factory '{$factoryName}' already exists in module '{$moduleName}'. Use --force to overwrite.");
            return self::FAILURE;
        }

        try {
            $this->createFactory($factoryPath, $moduleName, $factoryName, $modelName);
            $this->info("✅ Factory '{$factoryName}' created successfully in module '{$moduleName}'!");
            $this->displayNextSteps($moduleName, $factoryName, $modelName);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed to create factory: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function createFactory(string $factoryPath, string $moduleName, string $factoryName, ?string $modelName): void
    {
        $stubPath = $this->stubPath . '/factory.stub';

        if (!$this->files->exists($stubPath)) {
            $this->createFactoryFromTemplate($factoryPath, $moduleName, $factoryName, $modelName);
            return;
        }

        $content = $this->files->get($stubPath);
        $replacements = $this->getReplacements($moduleName, $factoryName, $modelName);

        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        $factoryDir = dirname($factoryPath);
        if (!$this->files->exists($factoryDir)) {
            $this->files->makeDirectory($factoryDir, 0755, true);
        }

        $this->files->put($factoryPath, $content);
    }

    private function createFactoryFromTemplate(string $factoryPath, string $moduleName, string $factoryName, ?string $modelName): void
    {
        $namespace = "Modules\\{$moduleName}\\Database\\Factories";
        $modelClass = $modelName ? "Modules\\{$moduleName}\\Domain\\Models\\{$modelName}" : null;
        $factoryDefinition = $this->generateFactoryDefinition($modelName);

        $content = "<?php

declare(strict_types=1);

namespace {$namespace};

use Illuminate\\Database\\Eloquent\\Factories\\Factory;
" . ($modelClass ? "use {$modelClass};\n" : "") . "
/**
 * @extends Factory<" . ($modelName ?: 'Model') . ">
 */
class {$factoryName} extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected \$model = " . ($modelName ? "{$modelName}::class" : 'null') . ";

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
{$factoryDefinition}
        ];
    }

    /**
     * Configure the factory.
     */
    public function configure(): static
    {
        return \$this->afterMaking(function (" . ($modelName ?: 'Model') . " \$model) {
            // Customize model after making
        })->afterCreating(function (" . ($modelName ?: 'Model') . " \$model) {
            // Customize model after creating
        });
    }
" . $this->generateFactoryStates($modelName) . "
}
";

        $factoryDir = dirname($factoryPath);
        if (!$this->files->exists($factoryDir)) {
            $this->files->makeDirectory($factoryDir, 0755, true);
        }

        $this->files->put($factoryPath, $content);
    }

    private function generateFactoryDefinition(?string $modelName): string
    {
        if (!$modelName) {
            return "            // Define your factory attributes here
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),";
        }

        // Generate common attributes based on model name
        $attributes = [];

        // Always include basic fields
        $attributes[] = "            'id' => " . $modelName . "Id::generate(),";
        $attributes[] = "            'name' => fake()->name(),";

        // Add specific attributes based on common patterns
        if (Str::contains(Str::lower($modelName), ['user', 'customer', 'person'])) {
            $attributes[] = "            'email' => fake()->unique()->safeEmail(),";
            $attributes[] = "            'email_verified_at' => now(),";
        }

        if (Str::contains(Str::lower($modelName), ['product', 'item'])) {
            $attributes[] = "            'price' => fake()->randomFloat(2, 10, 1000),";
            $attributes[] = "            'description' => fake()->sentence(),";
        }

        if (Str::contains(Str::lower($modelName), ['order', 'invoice'])) {
            $attributes[] = "            'total' => fake()->randomFloat(2, 50, 5000),";
            $attributes[] = "            'status' => fake()->randomElement(['pending', 'completed', 'cancelled']),";
        }

        // Add timestamps
        $attributes[] = "            'created_at' => now(),";
        $attributes[] = "            'updated_at' => now(),";

        return implode("\n", $attributes);
    }

    private function generateFactoryStates(?string $modelName): string
    {
        if (!$modelName) {
            return "
    /**
     * Indicate that the model should be in a specific state.
     */
    public function active(): static
    {
        return \$this->state(fn (array \$attributes) => [
            'status' => 'active',
        ]);
    }";
        }

        $states = [];

        // Generate common states based on model type
        if (Str::contains(Str::lower($modelName), ['user', 'customer'])) {
            $states[] = "
    /**
     * Indicate that the user is verified.
     */
    public function verified(): static
    {
        return \$this->state(fn (array \$attributes) => [
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the user is unverified.
     */
    public function unverified(): static
    {
        return \$this->state(fn (array \$attributes) => [
            'email_verified_at' => null,
        ]);
    }";
        }

        if (Str::contains(Str::lower($modelName), ['product', 'item'])) {
            $states[] = "
    /**
     * Indicate that the product is on sale.
     */
    public function onSale(): static
    {
        return \$this->state(fn (array \$attributes) => [
            'price' => fake()->randomFloat(2, 5, 50),
            'sale_price' => fake()->randomFloat(2, 1, 25),
        ]);
    }";
        }

        if (Str::contains(Str::lower($modelName), ['order'])) {
            $states[] = "
    /**
     * Indicate that the order is completed.
     */
    public function completed(): static
    {
        return \$this->state(fn (array \$attributes) => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the order is cancelled.
     */
    public function cancelled(): static
    {
        return \$this->state(fn (array \$attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }";
        }

        return implode('', $states);
    }

    private function getReplacements(string $moduleName, string $factoryName, ?string $modelName): array
    {
        return [
            '{{MODULE}}' => $moduleName,
            '{{FACTORY}}' => $factoryName,
            '{{MODEL}}' => $modelName ?: 'Model',
            '{{MODEL_VARIABLE}}' => $modelName ? Str::camel($modelName) : 'model',
            '{{NAMESPACE}}' => "Modules\\{$moduleName}",
        ];
    }

    private function displayNextSteps(string $moduleName, string $factoryName, ?string $modelName): void
    {
        $this->newLine();
        $this->line("📋 <comment>Next steps:</comment>");
        $this->line("1. Customize factory attributes in: modules/{$moduleName}/Database/Factories/{$factoryName}.php");
        $this->line("2. Use factory in tests:");

        if ($modelName) {
            $this->line("   <info>{$modelName}::factory()->create();</info>");
            $this->line("   <info>{$modelName}::factory()->count(3)->make();</info>");
            $this->line("3. Use factory states:");
            $this->line("   <info>{$modelName}::factory()->verified()->create();</info>");
        } else {
            $this->line("   <info>YourModel::factory()->create();</info>");
        }

        $this->line("4. Register factory in your test setup if needed");
        $this->newLine();
    }
}