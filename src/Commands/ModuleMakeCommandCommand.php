<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ModuleMakeCommandCommand extends Command
{
    protected $signature = 'module:make-command {module} {name} {--aggregate=} {--validation}';
    protected $description = 'Create a new CQRS command for a module';

    public function handle(): int
    {
        $moduleName = $this->argument('module');
        $commandName = $this->argument('name');
        $aggregateName = $this->option('aggregate');
        $withValidation = $this->option('validation');

        if (!$this->moduleExists($moduleName)) {
            $this->error("Module '{$moduleName}' does not exist.");

            return 1;
        }

        $this->createCommand($moduleName, $commandName, $aggregateName, $withValidation);
        $this->createCommandHandler($moduleName, $commandName, $aggregateName);

        $this->info("CQRS Command '{$commandName}' created successfully for module '{$moduleName}'.");

        return 0;
    }

    private function moduleExists(string $moduleName): bool
    {
        return is_dir(base_path("modules/{$moduleName}"));
    }

    private function createCommand(string $moduleName, string $commandName, ?string $aggregateName, bool $withValidation): void
    {
        $commandsDir = base_path("modules/{$moduleName}/Application/Commands");
        $this->ensureDirectoryExists($commandsDir);

        $commandFile = "{$commandsDir}/{$commandName}.php";
        $template = $this->getCommandTemplate();

        $replacements = [
            '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{COMMAND_NAME}}' => $commandName,
            '{{COMMAND_VARIABLE}}' => Str::camel($commandName),
            '{{AGGREGATE_NAME}}' => $aggregateName ?? 'Example',
            '{{VALIDATION_RULES}}' => $withValidation ? $this->getValidationRulesExample() : 'return [];',
            '{{VALIDATION_MESSAGES}}' => $withValidation ? $this->getValidationMessagesExample() : 'return [];',
            '{{PROPERTIES}}' => $this->getPropertiesExample($aggregateName),
            '{{CONSTRUCTOR_PARAMS}}' => $this->getConstructorParamsExample($aggregateName),
            '{{CONSTRUCTOR_ASSIGNMENTS}}' => $this->getConstructorAssignmentsExample($aggregateName),
            '{{TO_ARRAY_CONTENT}}' => $this->getToArrayExample($aggregateName),
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);
        file_put_contents($commandFile, $content);
    }

    private function createCommandHandler(string $moduleName, string $commandName, ?string $aggregateName): void
    {
        $handlersDir = base_path("modules/{$moduleName}/Application/Handlers/Commands");
        $this->ensureDirectoryExists($handlersDir);

        $handlerFile = "{$handlersDir}/{$commandName}Handler.php";
        $template = $this->getCommandHandlerTemplate();

        $replacements = [
            '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{COMMAND_NAME}}' => $commandName,
            '{{HANDLER_NAME}}' => $commandName . 'Handler',
            '{{AGGREGATE_NAME}}' => $aggregateName ?? 'Example',
            '{{AGGREGATE_VARIABLE}}' => $aggregateName ? Str::camel($aggregateName) : 'example',
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);
        file_put_contents($handlerFile, $content);
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }
    }

    private function getCommandTemplate(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace {{MODULE_NAMESPACE}}\Application\Commands;

            use TaiCrm\LaravelModularDdd\Foundation\Command;

            class {{COMMAND_NAME}} extends Command
            {
            {{PROPERTIES}}

                public function __construct({{CONSTRUCTOR_PARAMS}})
                {
            {{CONSTRUCTOR_ASSIGNMENTS}}
                    parent::__construct();
                }

                public function getValidationRules(): array
                {
                    {{VALIDATION_RULES}}
                }

                public function getValidationMessages(): array
                {
                    {{VALIDATION_MESSAGES}}
                }

                protected function toArray(): array
                {
                    return [
            {{TO_ARRAY_CONTENT}}
                    ];
                }
            }
            PHP;
    }

    private function getCommandHandlerTemplate(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace {{MODULE_NAMESPACE}}\Application\Handlers\Commands;

            use {{MODULE_NAMESPACE}}\Application\Commands\{{COMMAND_NAME}};
            use TaiCrm\LaravelModularDdd\Foundation\Contracts\CommandHandlerInterface;

            class {{HANDLER_NAME}} implements CommandHandlerInterface
            {
                public function handle({{COMMAND_NAME}} $command): mixed
                {
                    // TODO: Implement command handling logic
                    // Example: Create or update {{AGGREGATE_NAME}} aggregate

                    return true;
                }
            }
            PHP;
    }

    private function getPropertiesExample(?string $aggregateName): string
    {
        if (!$aggregateName) {
            return '    private string $exampleProperty;';
        }

        $variable = Str::camel($aggregateName);

        return "    private string \${$variable}Id;\n    private array \$data;";
    }

    private function getConstructorParamsExample(?string $aggregateName): string
    {
        if (!$aggregateName) {
            return 'string $exampleProperty';
        }

        $variable = Str::camel($aggregateName);

        return "string \${$variable}Id, array \$data";
    }

    private function getConstructorAssignmentsExample(?string $aggregateName): string
    {
        if (!$aggregateName) {
            return '        $this->exampleProperty = $exampleProperty;';
        }

        $variable = Str::camel($aggregateName);

        return "        \$this->{$variable}Id = \${$variable}Id;\n        \$this->data = \$data;";
    }

    private function getToArrayExample(?string $aggregateName): string
    {
        if (!$aggregateName) {
            return "            'example_property' => \$this->exampleProperty,";
        }

        $variable = Str::camel($aggregateName);
        $snakeCase = Str::snake($aggregateName);

        return "            '{$snakeCase}_id' => \$this->{$variable}Id,\n            'data' => \$this->data,";
    }

    private function getValidationRulesExample(): string
    {
        return <<<'PHP'
            return [
                        'example_property' => 'required|string|max:255',
                        'data' => 'array',
                    ];
            PHP;
    }

    private function getValidationMessagesExample(): string
    {
        return <<<'PHP'
            return [
                        'example_property.required' => 'The example property is required.',
                        'example_property.string' => 'The example property must be a string.',
                    ];
            PHP;
    }
}
