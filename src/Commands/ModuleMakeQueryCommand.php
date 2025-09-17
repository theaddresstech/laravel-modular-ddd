<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ModuleMakeQueryCommand extends Command
{
    protected $signature = 'module:make-query {module} {name} {--aggregate=} {--cacheable}';
    protected $description = 'Create a new CQRS query for a module';

    public function handle(): int
    {
        $moduleName = $this->argument('module');
        $queryName = $this->argument('name');
        $aggregateName = $this->option('aggregate');
        $cacheable = $this->option('cacheable');

        if (!$this->moduleExists($moduleName)) {
            $this->error("Module '{$moduleName}' does not exist.");

            return 1;
        }

        $this->createQuery($moduleName, $queryName, $aggregateName, $cacheable);
        $this->createQueryHandler($moduleName, $queryName, $aggregateName);

        $this->info("CQRS Query '{$queryName}' created successfully for module '{$moduleName}'.");

        return 0;
    }

    private function moduleExists(string $moduleName): bool
    {
        return is_dir(base_path("modules/{$moduleName}"));
    }

    private function createQuery(string $moduleName, string $queryName, ?string $aggregateName, bool $cacheable): void
    {
        $queriesDir = base_path("modules/{$moduleName}/Application/Queries");
        $this->ensureDirectoryExists($queriesDir);

        $queryFile = "{$queriesDir}/{$queryName}.php";
        $template = $this->getQueryTemplate();

        $replacements = [
            '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{QUERY_NAME}}' => $queryName,
            '{{QUERY_VARIABLE}}' => Str::camel($queryName),
            '{{AGGREGATE_NAME}}' => $aggregateName ?? 'Example',
            '{{CACHEABLE}}' => $cacheable ? 'true' : 'false',
            '{{CACHE_TTL}}' => $cacheable ? '600' : '300',
            '{{PROPERTIES}}' => $this->getPropertiesExample($aggregateName),
            '{{CONSTRUCTOR_PARAMS}}' => $this->getConstructorParamsExample($aggregateName),
            '{{CONSTRUCTOR_ASSIGNMENTS}}' => $this->getConstructorAssignmentsExample($aggregateName),
            '{{TO_ARRAY_CONTENT}}' => $this->getToArrayExample($aggregateName),
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);
        file_put_contents($queryFile, $content);
    }

    private function createQueryHandler(string $moduleName, string $queryName, ?string $aggregateName): void
    {
        $handlersDir = base_path("modules/{$moduleName}/Application/Handlers/Queries");
        $this->ensureDirectoryExists($handlersDir);

        $handlerFile = "{$handlersDir}/{$queryName}Handler.php";
        $template = $this->getQueryHandlerTemplate();

        $replacements = [
            '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{QUERY_NAME}}' => $queryName,
            '{{HANDLER_NAME}}' => $queryName . 'Handler',
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

    private function getQueryTemplate(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace {{MODULE_NAMESPACE}}\Application\Queries;

            use TaiCrm\LaravelModularDdd\Foundation\Query;

            class {{QUERY_NAME}} extends Query
            {
            {{PROPERTIES}}

                public function __construct({{CONSTRUCTOR_PARAMS}})
                {
            {{CONSTRUCTOR_ASSIGNMENTS}}
                    parent::__construct();
                }

                protected function isCacheable(): bool
                {
                    return {{CACHEABLE}};
                }

                protected function getDefaultCacheTtl(): int
                {
                    return {{CACHE_TTL}};
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

    private function getQueryHandlerTemplate(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace {{MODULE_NAMESPACE}}\Application\Handlers\Queries;

            use {{MODULE_NAMESPACE}}\Application\Queries\{{QUERY_NAME}};
            use TaiCrm\LaravelModularDdd\Foundation\Contracts\QueryHandlerInterface;

            class {{HANDLER_NAME}} implements QueryHandlerInterface
            {
                public function handle({{QUERY_NAME}} $query): mixed
                {
                    // TODO: Implement query handling logic
                    // Example: Fetch {{AGGREGATE_NAME}} data

                    return [];
                }
            }
            PHP;
    }

    private function getPropertiesExample(?string $aggregateName): string
    {
        if (!$aggregateName) {
            return '    private ?string $filter = null;';
        }

        $variable = Str::camel($aggregateName);

        return "    private ?string \${$variable}Id = null;\n    private array \$filters = [];";
    }

    private function getConstructorParamsExample(?string $aggregateName): string
    {
        if (!$aggregateName) {
            return '?string $filter = null';
        }

        $variable = Str::camel($aggregateName);

        return "?string \${$variable}Id = null, array \$filters = []";
    }

    private function getConstructorAssignmentsExample(?string $aggregateName): string
    {
        if (!$aggregateName) {
            return '        $this->filter = $filter;';
        }

        $variable = Str::camel($aggregateName);

        return "        \$this->{$variable}Id = \${$variable}Id;\n        \$this->filters = \$filters;";
    }

    private function getToArrayExample(?string $aggregateName): string
    {
        if (!$aggregateName) {
            return "            'filter' => \$this->filter,";
        }

        $variable = Str::camel($aggregateName);
        $snakeCase = Str::snake($aggregateName);

        return "            '{$snakeCase}_id' => \$this->{$variable}Id,\n            'filters' => \$this->filters,";
    }
}
