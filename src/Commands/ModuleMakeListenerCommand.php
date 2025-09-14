<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ModuleMakeListenerCommand extends Command
{
    protected $signature = 'module:make-listener
                            {module : The name of the module}
                            {name : The name of the listener}
                            {--event= : The event this listener handles}
                            {--queued : Make the listener queued}
                            {--force : Overwrite existing listener}';

    protected $description = 'Create a new event listener for a module';

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
        $listenerName = Str::studly($this->argument('name'));
        $eventName = $this->option('event') ? Str::studly($this->option('event')) : null;
        $isQueued = $this->option('queued');

        $modulePath = $this->modulesPath . '/' . $moduleName;

        if (!$this->files->exists($modulePath)) {
            $this->error("Module '{$moduleName}' does not exist. Create it first using module:make");
            return self::FAILURE;
        }

        $listenerPath = $modulePath . '/Application/Listeners/' . $listenerName . '.php';

        if ($this->files->exists($listenerPath) && !$this->option('force')) {
            $this->error("Listener '{$listenerName}' already exists in module '{$moduleName}'. Use --force to overwrite.");
            return self::FAILURE;
        }

        try {
            $this->createListener($listenerPath, $moduleName, $listenerName, $eventName, $isQueued);
            $this->info("✅ Listener '{$listenerName}' created successfully in module '{$moduleName}'!");
            $this->displayNextSteps($moduleName, $listenerName, $eventName);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed to create listener: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function createListener(string $listenerPath, string $moduleName, string $listenerName, ?string $eventName, bool $isQueued): void
    {
        $stubPath = $this->stubPath . '/event-listener.stub';

        if (!$this->files->exists($stubPath)) {
            $this->createListenerFromTemplate($listenerPath, $moduleName, $listenerName, $eventName, $isQueued);
            return;
        }

        $content = $this->files->get($stubPath);
        $replacements = $this->getReplacements($moduleName, $listenerName, $eventName, $isQueued);

        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        $listenerDir = dirname($listenerPath);
        if (!$this->files->exists($listenerDir)) {
            $this->files->makeDirectory($listenerDir, 0755, true);
        }

        $this->files->put($listenerPath, $content);
    }

    private function createListenerFromTemplate(string $listenerPath, string $moduleName, string $listenerName, ?string $eventName, bool $isQueued): void
    {
        $namespace = "Modules\\{$moduleName}\\Application\\Listeners";
        $eventClass = $eventName ? "\\Modules\\{$moduleName}\\Domain\\Events\\{$eventName}" : 'TaiCrm\\LaravelModularDdd\\Foundation\\Contracts\\DomainEventInterface';
        $eventParam = $eventName ? $eventName : 'DomainEventInterface';
        $eventVariable = $eventName ? Str::camel($eventName) : 'event';

        $implements = '';
        $uses = '';

        if ($isQueued) {
            $implements = ' implements ShouldQueue';
            $uses = "use Illuminate\\Contracts\\Queue\\ShouldQueue;\nuse Illuminate\\Queue\\InteractsWithQueue;\n";
        }

        $content = "<?php

declare(strict_types=1);

namespace {$namespace};

{$uses}use {$eventClass};
use Psr\\Log\\LoggerInterface;

final class {$listenerName}{$implements}
{" . ($isQueued ? "
    use InteractsWithQueue;
" : "") . "
    public function __construct(
        private LoggerInterface \$logger
    ) {}

    public function handle({$eventParam} \${$eventVariable}): void
    {
        \$this->logger->info('{$listenerName} handling event', [
            'event_id' => \${$eventVariable}->getEventId(),
            'event_type' => \${$eventVariable}->getEventType(),
        ]);

        try {
            // TODO: Implement your event handling logic here
            \$this->process{$eventName}(\${$eventVariable});

        } catch (\\Exception \$e) {
            \$this->logger->error('Error in {$listenerName}', [
                'event_id' => \${$eventVariable}->getEventId(),
                'error' => \$e->getMessage(),
                'exception' => \$e,
            ]);

            throw \$e;
        }
    }

    private function process{$eventName}({$eventParam} \${$eventVariable}): void
    {
        // Implement your business logic here
        // Example: Send email, update database, call external API, etc.
    }
}
";

        $listenerDir = dirname($listenerPath);
        if (!$this->files->exists($listenerDir)) {
            $this->files->makeDirectory($listenerDir, 0755, true);
        }

        $this->files->put($listenerPath, $content);
    }

    private function getReplacements(string $moduleName, string $listenerName, ?string $eventName, bool $isQueued): array
    {
        return [
            '{{MODULE}}' => $moduleName,
            '{{LISTENER}}' => $listenerName,
            '{{EVENT}}' => $eventName ?: 'DomainEvent',
            '{{EVENT_VARIABLE}}' => $eventName ? Str::camel($eventName) : 'event',
            '{{NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{QUEUED}}' => $isQueued ? 'true' : 'false',
        ];
    }

    private function displayNextSteps(string $moduleName, string $listenerName, ?string $eventName): void
    {
        $this->newLine();
        $this->line("📋 <comment>Next steps:</comment>");
        $this->line("1. Implement the event handling logic in: modules/{$moduleName}/Application/Listeners/{$listenerName}.php");
        $this->line("2. Register the listener in your service provider:");

        if ($eventName) {
            $this->line("   <info>Event::listen('{$eventName}', {$listenerName}::class);</info>");
        } else {
            $this->line("   <info>Event::listen('YourEvent', {$listenerName}::class);</info>");
        }

        $this->line("3. Test the listener by dispatching the event");
        $this->newLine();
    }
}