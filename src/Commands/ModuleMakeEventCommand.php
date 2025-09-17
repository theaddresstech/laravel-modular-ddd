<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ModuleMakeEventCommand extends Command
{
    protected $signature = 'module:make-event
                            {module : The name of the module}
                            {name : The name of the event}
                            {--aggregate= : The aggregate that triggers this event}
                            {--force : Overwrite existing event}';
    protected $description = 'Create a new domain event for a module';

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
        $eventName = Str::studly($this->argument('name'));
        $aggregate = $this->option('aggregate') ?: $moduleName;

        $modulePath = $this->modulesPath . '/' . $moduleName;

        if (!$this->files->exists($modulePath)) {
            $this->error("Module '{$moduleName}' does not exist. Create it first using module:make");

            return self::FAILURE;
        }

        $eventPath = $modulePath . '/Domain/Events/' . $eventName . '.php';

        if ($this->files->exists($eventPath) && !$this->option('force')) {
            $this->error("Event '{$eventName}' already exists in module '{$moduleName}'. Use --force to overwrite.");

            return self::FAILURE;
        }

        try {
            $this->createEvent($eventPath, $moduleName, $eventName, $aggregate);
            $this->info("✅ Event '{$eventName}' created successfully in module '{$moduleName}'!");
            $this->displayNextSteps($moduleName, $eventName);

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('❌ Failed to create event: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function createEvent(string $eventPath, string $moduleName, string $eventName, string $aggregate): void
    {
        $stubPath = $this->stubPath . '/domain-event.stub';

        if (!$this->files->exists($stubPath)) {
            $this->createEventFromTemplate($eventPath, $moduleName, $eventName, $aggregate);

            return;
        }

        $content = $this->files->get($stubPath);
        $replacements = $this->getReplacements($moduleName, $eventName, $aggregate);

        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        $eventDir = dirname($eventPath);
        if (!$this->files->exists($eventDir)) {
            $this->files->makeDirectory($eventDir, 0o755, true);
        }

        $this->files->put($eventPath, $content);
    }

    private function createEventFromTemplate(string $eventPath, string $moduleName, string $eventName, string $aggregate): void
    {
        $namespace = "Modules\\{$moduleName}\\Domain\\Events";

        $content = "<?php

declare(strict_types=1);

namespace {$namespace};

use TaiCrm\\LaravelModularDdd\\Foundation\\DomainEvent;

final readonly class {$eventName} extends DomainEvent
{
    public function __construct(
        private string \${$this->getAggregateVariable($aggregate)}Id,
        // Add your event-specific properties here
    ) {
        parent::__construct();
    }

    public function get{$aggregate}Id(): string
    {
        return \$this->{$this->getAggregateVariable($aggregate)}Id;
    }

    public function getEventType(): string
    {
        return '{$eventName}';
    }

    public function getPayload(): array
    {
        return [
            '{$this->getAggregateVariable($aggregate)}_id' => \$this->{$this->getAggregateVariable($aggregate)}Id,
            // Add your event data here
        ];
    }

    public static function raise(
        string \${$this->getAggregateVariable($aggregate)}Id
        // Add parameters as needed
    ): self {
        return new self(
            \${$this->getAggregateVariable($aggregate)}Id
        );
    }
}
";

        $eventDir = dirname($eventPath);
        if (!$this->files->exists($eventDir)) {
            $this->files->makeDirectory($eventDir, 0o755, true);
        }

        $this->files->put($eventPath, $content);
    }

    private function getReplacements(string $moduleName, string $eventName, string $aggregate): array
    {
        return [
            '{{MODULE}}' => $moduleName,
            '{{EVENT}}' => $eventName,
            '{{AGGREGATE}}' => Str::studly($aggregate),
            '{{AGGREGATE_VARIABLE}}' => $this->getAggregateVariable($aggregate),
            '{{NAMESPACE}}' => "Modules\\{$moduleName}",
        ];
    }

    private function getAggregateVariable(string $aggregate): string
    {
        return Str::camel($aggregate);
    }

    private function displayNextSteps(string $moduleName, string $eventName): void
    {
        $this->newLine();
        $this->line('📋 <comment>Next steps:</comment>');
        $this->line("1. Update the event properties and payload in: modules/{$moduleName}/Domain/Events/{$eventName}.php");
        $this->line("2. Add the event to your aggregate: <info>\$this->apply({$eventName}::raise(\$id));</info>");
        $this->line("3. Create event listener: <info>php artisan module:make-listener {$moduleName} {$eventName}Listener --event={$eventName}</info>");
        $this->line('4. Register the listener in your service provider');
        $this->newLine();
    }
}
