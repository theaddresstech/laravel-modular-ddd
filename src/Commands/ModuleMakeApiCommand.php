<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ModuleMakeApiCommand extends Command
{
    protected $signature = 'module:make-api {module} {resource} {--auth} {--validation} {--swagger=1} {--api-version=} {--all-versions} {--examples} {--comprehensive}';
    protected $description = 'Generate complete REST API scaffolding for a module resource with comprehensive Swagger documentation';

    public function handle(): int
    {
        $moduleName = $this->argument('module');
        $resourceName = $this->argument('resource');
        $withAuth = $this->option('auth');
        $withValidation = $this->option('validation');
        $withSwagger = $this->option('swagger') !== false; // Default to true
        $withExamples = $this->option('examples');
        $withComprehensive = $this->option('comprehensive');
        $specificVersion = $this->option('api-version');
        $allVersions = $this->option('all-versions');

        if (!$this->moduleExists($moduleName)) {
            $this->error("Module '{$moduleName}' does not exist.");
            return 1;
        }

        // Determine which versions to generate
        $versions = $this->getTargetVersions($specificVersion, $allVersions);

        if (empty($versions)) {
            $this->error("No valid API versions found to generate.");
            return 1;
        }

        $this->info("Generating API scaffolding for {$resourceName} in {$moduleName} module...");
        $this->info("Target versions: " . implode(', ', $versions));

        // Generate for each version
        foreach ($versions as $version) {
            $this->generateVersionedApi($moduleName, $resourceName, $version, $withAuth, $withValidation, $withSwagger, $withComprehensive, $withExamples);
        }

        // Generate shared components (not version-specific)
        $this->generateRequests($moduleName, $resourceName, $withValidation, $withComprehensive);
        $this->generateResource($moduleName, $resourceName, $withComprehensive);
        $this->generateCommands($moduleName, $resourceName);
        $this->generateQueries($moduleName, $resourceName);
        $this->generateHandlers($moduleName, $resourceName);

        if ($withSwagger) {
            foreach ($versions as $version) {
                $this->generateSwaggerDocs($moduleName, $resourceName, $version);
            }
        }

        $this->info("✅ API scaffolding generated successfully!");
        $this->displayGeneratedFiles($resourceName, $versions, $withSwagger);

        return 0;
    }

    private function moduleExists(string $moduleName): bool
    {
        return is_dir(base_path("modules/{$moduleName}"));
    }

    private function getTargetVersions(?string $specificVersion, bool $allVersions): array
    {
        if ($allVersions) {
            return config('modular-ddd.api.versions.supported', ['v1']);
        }

        if ($specificVersion) {
            $supportedVersions = config('modular-ddd.api.versions.supported', ['v1']);
            if (!in_array($specificVersion, $supportedVersions)) {
                $this->warn("Version '{$specificVersion}' is not in supported versions. Generating anyway.");
            }
            return [$specificVersion];
        }

        // Default to latest version
        $defaultVersion = config('modular-ddd.api.versions.latest', config('modular-ddd.api.version', 'v1'));
        return [$defaultVersion];
    }

    private function generateVersionedApi(string $moduleName, string $resourceName, string $version, bool $withAuth, bool $withValidation, bool $withSwagger, bool $withComprehensive = false, bool $withExamples = false): void
    {
        $this->info("Generating API for version {$version}...");

        $this->generateController($moduleName, $resourceName, $version, $withAuth, $withValidation, $withSwagger, $withComprehensive, $withExamples);
        $this->generateRoutes($moduleName, $resourceName, $version, $withAuth);
    }

    private function displayGeneratedFiles(string $resourceName, array $versions, bool $withSwagger): void
    {
        $this->line("📝 Generated files:");

        foreach ($versions as $version) {
            $this->line("   📁 Version {$version}:");
            $this->line("     - Controller: Http/Controllers/Api/{$version}/{$resourceName}Controller.php");
            $this->line("     - Routes: Routes/api.php (updated with {$version} routes)");
            if ($withSwagger) {
                $this->line("     - Swagger: Docs/{$version}/{$resourceName}Api.php");
            }
        }

        $this->line("   📁 Shared Components:");
        $this->line("     - Requests: Http/Requests/{$resourceName}/");
        $this->line("     - Resource: Http/Resources/{$resourceName}Resource.php");
        $this->line("     - Commands & Queries: Application/");
        $this->line("     - Handlers: Application/Handlers/");
    }

    private function generateController(string $moduleName, string $resourceName, string $apiVersion, bool $withAuth, bool $withValidation, bool $withSwagger, bool $withComprehensive = false, bool $withExamples = false): void
    {
        $controllersDir = base_path("modules/{$moduleName}/Http/Controllers/Api/{$apiVersion}");
        $this->ensureDirectoryExists($controllersDir);

        $controllerFile = "{$controllersDir}/{$resourceName}Controller.php";

        // Use comprehensive Swagger template if enabled or if Swagger is enabled by default
        $template = ($withComprehensive || $withSwagger) ?
            $this->getComprehensiveSwaggerControllerTemplate() :
            $this->getControllerTemplate();

        $replacements = [
            '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{API_VERSION}}' => $apiVersion,
            '{{RESOURCE_NAME}}' => $resourceName,
            '{{RESOURCE_VARIABLE}}' => Str::camel($resourceName),
            '{{RESOURCE_SNAKE}}' => Str::snake($resourceName),
            '{{RESOURCE_KEBAB}}' => Str::kebab($resourceName),
            '{{AUTH_MIDDLEWARE}}' => $withAuth ? "->middleware('auth:api')" : '',
            '{{VALIDATION_IMPORTS}}' => $withValidation ? $this->getValidationImports($moduleName, $resourceName) : '',
            '{{REQUEST_CLASSES}}' => $withValidation ? $this->getRequestClasses($resourceName) : 'Request',
            '{{SWAGGER_ANNOTATIONS}}' => $withSwagger ? $this->getSwaggerAnnotations($resourceName, $apiVersion) : '',
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);
        file_put_contents($controllerFile, $content);
    }

    private function generateRequests(string $moduleName, string $resourceName, bool $withValidation, bool $withComprehensive = false): void
    {
        if (!$withValidation) {
            return;
        }

        $requestsDir = base_path("modules/{$moduleName}/Http/Requests/{$resourceName}");
        $this->ensureDirectoryExists($requestsDir);

        // Create Request - use comprehensive Swagger version if enabled
        $createRequestFile = "{$requestsDir}/Create{$resourceName}Request.php";
        $createTemplate = $withComprehensive ?
            $this->getComprehensiveCreateRequestTemplate() :
            $this->getCreateRequestTemplate();
        $createReplacements = [
            '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{RESOURCE_NAME}}' => $resourceName,
            '{{RESOURCE_VARIABLE}}' => Str::camel($resourceName),
            '{{VALIDATION_RULES}}' => $withComprehensive ?
                $this->getComprehensiveCreateValidationRules($resourceName) :
                $this->getCreateValidationRules($resourceName),
        ];
        $createContent = str_replace(array_keys($createReplacements), array_values($createReplacements), $createTemplate);
        file_put_contents($createRequestFile, $createContent);

        // Update Request - use comprehensive Swagger version if enabled
        $updateRequestFile = "{$requestsDir}/Update{$resourceName}Request.php";
        $updateTemplate = $withComprehensive ?
            $this->getComprehensiveUpdateRequestTemplate() :
            $this->getUpdateRequestTemplate();
        $updateReplacements = [
            '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{RESOURCE_NAME}}' => $resourceName,
            '{{RESOURCE_VARIABLE}}' => Str::camel($resourceName),
            '{{VALIDATION_RULES}}' => $withComprehensive ?
                $this->getComprehensiveUpdateValidationRules($resourceName) :
                $this->getUpdateValidationRules($resourceName),
            '{{UPDATE_VALIDATION_RULES}}' => $withComprehensive ?
                $this->getComprehensiveUpdateValidationRules($resourceName) :
                $this->getUpdateValidationRules($resourceName),
        ];
        $updateContent = str_replace(array_keys($updateReplacements), array_values($updateReplacements), $updateTemplate);
        file_put_contents($updateRequestFile, $updateContent);
    }

    private function generateResource(string $moduleName, string $resourceName, bool $withComprehensive = false): void
    {
        $resourcesDir = base_path("modules/{$moduleName}/Http/Resources");
        $this->ensureDirectoryExists($resourcesDir);

        $resourceFile = "{$resourcesDir}/{$resourceName}Resource.php";
        $template = $withComprehensive ?
            $this->getComprehensiveResourceTemplate() :
            $this->getResourceTemplate();

        $replacements = [
            '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{RESOURCE_NAME}}' => $resourceName,
            '{{RESOURCE_VARIABLE}}' => Str::camel($resourceName),
            '{{RESOURCE_KEBAB}}' => Str::kebab($resourceName),
            '{{RESOURCE_ATTRIBUTES}}' => $withComprehensive ?
                $this->getComprehensiveResourceAttributes($resourceName) :
                $this->getResourceAttributes($resourceName),
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);
        file_put_contents($resourceFile, $content);
    }

    private function generateRoutes(string $moduleName, string $resourceName, string $apiVersion, bool $withAuth): void
    {
        $routesFile = base_path("modules/{$moduleName}/Routes/api.php");
        $this->ensureDirectoryExists(dirname($routesFile));

        $routeTemplate = $this->getRouteTemplate();
        $replacements = [
            '{{RESOURCE_NAME}}' => $resourceName,
            '{{RESOURCE_KEBAB}}' => Str::kebab($resourceName),
            '{{API_VERSION}}' => $apiVersion,
            '{{AUTH_MIDDLEWARE}}' => $withAuth ? "->middleware('auth:api')" : '',
            '{{AUTH_MIDDLEWARE_ARRAY}}' => $withAuth ? ", 'auth:api'" : '',
        ];

        $routeContent = str_replace(array_keys($replacements), array_values($replacements), $routeTemplate);

        $controllerNamespace = "Modules\\{$moduleName}\\Http\\Controllers\\Api\\{$apiVersion}\\{$resourceName}Controller";

        if (file_exists($routesFile)) {
            $existingContent = file_get_contents($routesFile);
            // Check specifically for the versioned route pattern
            $versionedRoutePattern = "Route::prefix('api/{$apiVersion}')";
            if (!str_contains($existingContent, $versionedRoutePattern) || !str_contains($existingContent, "Route::apiResource('" . Str::kebab($resourceName) . "', " . $resourceName . "Controller::class")) {
                // Use alias for versioned controller to avoid namespace collision
                $controllerAlias = "{$resourceName}Controller" . ucfirst($apiVersion);
                $importLine = "use {$controllerNamespace} as {$controllerAlias};";

                // Add controller import if not exists
                if (!str_contains($existingContent, "use {$controllerNamespace}")) {
                    $existingContent = str_replace("use Illuminate\Support\Facades\Route;", "use Illuminate\Support\Facades\Route;\n{$importLine}", $existingContent);
                }

                // Update route content to use aliased controller
                $routeContent = str_replace("{$resourceName}Controller::class", "{$controllerAlias}::class", $routeContent);
                file_put_contents($routesFile, $existingContent . "\n" . $routeContent);
            }
        } else {
            $controllerAlias = "{$resourceName}Controller" . ucfirst($apiVersion);
            $importLine = "use {$controllerNamespace} as {$controllerAlias};";
            $routeContent = str_replace("{$resourceName}Controller::class", "{$controllerAlias}::class", $routeContent);
            $fullRouteFile = "<?php\n\n/*\n|--------------------------------------------------------------------------\n| {$moduleName} API Routes ({$apiVersion})\n|--------------------------------------------------------------------------\n*/\n\nuse Illuminate\Support\Facades\Route;\n{$importLine}\n\n" . $routeContent;
            file_put_contents($routesFile, $fullRouteFile);
        }
    }

    private function generateCommands(string $moduleName, string $resourceName): void
    {
        $commandsDir = base_path("modules/{$moduleName}/Application/Commands");
        $this->ensureDirectoryExists($commandsDir);

        // Create Command
        $createCommandFile = "{$commandsDir}/Create{$resourceName}Command.php";
        $createTemplate = $this->getCreateCommandTemplate();
        $createReplacements = [
            '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{RESOURCE_NAME}}' => $resourceName,
            '{{COMMAND_PROPERTIES}}' => $this->getCommandProperties($resourceName),
            '{{CONSTRUCTOR_PARAMS}}' => $this->getConstructorParams($resourceName),
            '{{CONSTRUCTOR_ASSIGNMENTS}}' => $this->getConstructorAssignments($resourceName),
            '{{TO_ARRAY_CONTENT}}' => $this->getToArrayContent($resourceName),
        ];
        $createContent = str_replace(array_keys($createReplacements), array_values($createReplacements), $createTemplate);
        file_put_contents($createCommandFile, $createContent);

        // Update Command
        $updateCommandFile = "{$commandsDir}/Update{$resourceName}Command.php";
        $updateTemplate = $this->getUpdateCommandTemplate();
        $updateReplacements = [
            '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{RESOURCE_NAME}}' => $resourceName,
            '{{COMMAND_PROPERTIES}}' => $this->getUpdateCommandProperties($resourceName),
            '{{CONSTRUCTOR_PARAMS}}' => $this->getUpdateConstructorParams($resourceName),
            '{{CONSTRUCTOR_ASSIGNMENTS}}' => $this->getUpdateConstructorAssignments($resourceName),
            '{{TO_ARRAY_CONTENT}}' => $this->getUpdateToArrayContent($resourceName),
        ];
        $updateContent = str_replace(array_keys($updateReplacements), array_values($updateReplacements), $updateTemplate);
        file_put_contents($updateCommandFile, $updateContent);

        // Delete Command
        $deleteCommandFile = "{$commandsDir}/Delete{$resourceName}Command.php";
        $deleteTemplate = $this->getDeleteCommandTemplate();
        $deleteReplacements = [
            '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{RESOURCE_NAME}}' => $resourceName,
            '{{RESOURCE_VARIABLE}}' => Str::camel($resourceName),
        ];
        $deleteContent = str_replace(array_keys($deleteReplacements), array_values($deleteReplacements), $deleteTemplate);
        file_put_contents($deleteCommandFile, $deleteContent);
    }

    private function generateQueries(string $moduleName, string $resourceName): void
    {
        $queriesDir = base_path("modules/{$moduleName}/Application/Queries");
        $this->ensureDirectoryExists($queriesDir);

        // Get Single Query
        $getQueryFile = "{$queriesDir}/Get{$resourceName}Query.php";
        $getTemplate = $this->getGetQueryTemplate();
        $getReplacements = [
            '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{RESOURCE_NAME}}' => $resourceName,
            '{{RESOURCE_VARIABLE}}' => Str::camel($resourceName),
        ];
        $getContent = str_replace(array_keys($getReplacements), array_values($getReplacements), $getTemplate);
        file_put_contents($getQueryFile, $getContent);

        // List Query
        $listQueryFile = "{$queriesDir}/List{$resourceName}Query.php";
        $listTemplate = $this->getListQueryTemplate();
        $listReplacements = [
            '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{RESOURCE_NAME}}' => $resourceName,
        ];
        $listContent = str_replace(array_keys($listReplacements), array_values($listReplacements), $listTemplate);
        file_put_contents($listQueryFile, $listContent);
    }

    private function generateHandlers(string $moduleName, string $resourceName): void
    {
        // Command Handlers
        $commandHandlersDir = base_path("modules/{$moduleName}/Application/Handlers/Commands");
        $this->ensureDirectoryExists($commandHandlersDir);

        $handlers = ['Create', 'Update', 'Delete'];
        foreach ($handlers as $action) {
            $handlerFile = "{$commandHandlersDir}/{$action}{$resourceName}CommandHandler.php";
            $template = $this->getCommandHandlerTemplate($action);
            $replacements = [
                '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
                '{{RESOURCE_NAME}}' => $resourceName,
                '{{ACTION}}' => $action,
                '{{RESOURCE_VARIABLE}}' => Str::camel($resourceName),
            ];
            $content = str_replace(array_keys($replacements), array_values($replacements), $template);
            file_put_contents($handlerFile, $content);
        }

        // Query Handlers
        $queryHandlersDir = base_path("modules/{$moduleName}/Application/Handlers/Queries");
        $this->ensureDirectoryExists($queryHandlersDir);

        $queryHandlers = ['Get', 'List'];
        foreach ($queryHandlers as $action) {
            $handlerFile = "{$queryHandlersDir}/{$action}{$resourceName}QueryHandler.php";
            $template = $this->getQueryHandlerTemplate($action);
            $replacements = [
                '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
                '{{RESOURCE_NAME}}' => $resourceName,
                '{{ACTION}}' => $action,
                '{{RESOURCE_VARIABLE}}' => Str::camel($resourceName),
            ];
            $content = str_replace(array_keys($replacements), array_values($replacements), $template);
            file_put_contents($handlerFile, $content);
        }
    }

    private function generateSwaggerDocs(string $moduleName, string $resourceName, string $apiVersion): void
    {
        $docsDir = base_path("modules/{$moduleName}/Docs/{$apiVersion}");
        $this->ensureDirectoryExists($docsDir);

        $swaggerFile = "{$docsDir}/{$resourceName}Api.php";
        $template = $this->getSwaggerTemplate();
        $replacements = [
            '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{RESOURCE_NAME}}' => $resourceName,
            '{{RESOURCE_KEBAB}}' => Str::kebab($resourceName),
            '{{RESOURCE_VARIABLE}}' => Str::camel($resourceName),
            '{{API_VERSION}}' => $apiVersion,
        ];
        $content = str_replace(array_keys($replacements), array_values($replacements), $template);
        file_put_contents($swaggerFile, $content);
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function getControllerTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace {{MODULE_NAMESPACE}}\Http\Controllers\Api\{{API_VERSION}};

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use TaiCrm\LaravelModularDdd\Foundation\CommandBus;
use TaiCrm\LaravelModularDdd\Foundation\QueryBus;
use {{MODULE_NAMESPACE}}\Application\Commands\Create{{RESOURCE_NAME}}Command;
use {{MODULE_NAMESPACE}}\Application\Commands\Update{{RESOURCE_NAME}}Command;
use {{MODULE_NAMESPACE}}\Application\Commands\Delete{{RESOURCE_NAME}}Command;
use {{MODULE_NAMESPACE}}\Application\Queries\Get{{RESOURCE_NAME}}Query;
use {{MODULE_NAMESPACE}}\Application\Queries\List{{RESOURCE_NAME}}Query;
use {{MODULE_NAMESPACE}}\Http\Resources\{{RESOURCE_NAME}}Resource;
{{VALIDATION_IMPORTS}}{{SWAGGER_ANNOTATIONS}}

class {{RESOURCE_NAME}}Controller extends Controller
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = new List{{RESOURCE_NAME}}Query(
            $request->get('filters', []),
            $request->get('sort', 'created_at'),
            $request->get('direction', 'desc'),
            $request->get('per_page', 15)
        );

        ${{RESOURCE_VARIABLE}}s = $this->queryBus->ask($query);

        return response()->json([
            'data' => {{RESOURCE_NAME}}Resource::collection(${{RESOURCE_VARIABLE}}s->items()),
            'meta' => [
                'current_page' => ${{RESOURCE_VARIABLE}}s->currentPage(),
                'last_page' => ${{RESOURCE_VARIABLE}}s->lastPage(),
                'per_page' => ${{RESOURCE_VARIABLE}}s->perPage(),
                'total' => ${{RESOURCE_VARIABLE}}s->total(),
            ]
        ]);
    }

    public function store({{REQUEST_CLASSES}} $request): JsonResponse
    {
        $command = new Create{{RESOURCE_NAME}}Command(
            ...$request->validated()
        );

        ${{RESOURCE_VARIABLE}} = $this->commandBus->dispatch($command);

        return response()->json([
            'data' => new {{RESOURCE_NAME}}Resource(${{RESOURCE_VARIABLE}}),
            'message' => '{{RESOURCE_NAME}} created successfully'
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $query = new Get{{RESOURCE_NAME}}Query($id);
        ${{RESOURCE_VARIABLE}} = $this->queryBus->ask($query);

        if (!${{RESOURCE_VARIABLE}}) {
            return response()->json(['message' => '{{RESOURCE_NAME}} not found'], 404);
        }

        return response()->json([
            'data' => new {{RESOURCE_NAME}}Resource(${{RESOURCE_VARIABLE}})
        ]);
    }

    public function update({{REQUEST_CLASSES}} $request, string $id): JsonResponse
    {
        $command = new Update{{RESOURCE_NAME}}Command(
            $id,
            $request->validated()
        );

        ${{RESOURCE_VARIABLE}} = $this->commandBus->dispatch($command);

        return response()->json([
            'data' => new {{RESOURCE_NAME}}Resource(${{RESOURCE_VARIABLE}}),
            'message' => '{{RESOURCE_NAME}} updated successfully'
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $command = new Delete{{RESOURCE_NAME}}Command($id);
        $this->commandBus->dispatch($command);

        return response()->json([
            'message' => '{{RESOURCE_NAME}} deleted successfully'
        ]);
    }
}
PHP;
    }

    private function getCreateRequestTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace {{MODULE_NAMESPACE}}\Http\Requests\{{RESOURCE_NAME}};

use Illuminate\Foundation\Http\FormRequest;

class Create{{RESOURCE_NAME}}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
{{VALIDATION_RULES}}
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'name.string' => 'The name must be a string.',
            'name.max' => 'The name may not be greater than 255 characters.',
        ];
    }
}
PHP;
    }

    private function getUpdateRequestTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace {{MODULE_NAMESPACE}}\Http\Requests\{{RESOURCE_NAME}};

use Illuminate\Foundation\Http\FormRequest;

class Update{{RESOURCE_NAME}}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
{{VALIDATION_RULES}}
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'The name must be a string.',
            'name.max' => 'The name may not be greater than 255 characters.',
        ];
    }
}
PHP;
    }

    private function getResourceTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace {{MODULE_NAMESPACE}}\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class {{RESOURCE_NAME}}Resource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
{{RESOURCE_ATTRIBUTES}}
        ];
    }
}
PHP;
    }

    private function getRouteTemplate(): string
    {
        return <<<'PHP'
Route::prefix('api/{{API_VERSION}}')
    ->middleware(['api', 'api.version'{{AUTH_MIDDLEWARE_ARRAY}}])
    ->group(function () {
        Route::apiResource('{{RESOURCE_KEBAB}}', {{RESOURCE_NAME}}Controller::class);
    });
PHP;
    }

    private function getCreateCommandTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace {{MODULE_NAMESPACE}}\Application\Commands;

use TaiCrm\LaravelModularDdd\Foundation\Command;

class Create{{RESOURCE_NAME}}Command extends Command
{
{{COMMAND_PROPERTIES}}

    public function __construct({{CONSTRUCTOR_PARAMS}})
    {
{{CONSTRUCTOR_ASSIGNMENTS}}
        parent::__construct();
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

    private function getUpdateCommandTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace {{MODULE_NAMESPACE}}\Application\Commands;

use TaiCrm\LaravelModularDdd\Foundation\Command;

class Update{{RESOURCE_NAME}}Command extends Command
{
{{COMMAND_PROPERTIES}}

    public function __construct({{CONSTRUCTOR_PARAMS}})
    {
{{CONSTRUCTOR_ASSIGNMENTS}}
        parent::__construct();
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

    private function getDeleteCommandTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace {{MODULE_NAMESPACE}}\Application\Commands;

use TaiCrm\LaravelModularDdd\Foundation\Command;

class Delete{{RESOURCE_NAME}}Command extends Command
{
    private string ${{RESOURCE_VARIABLE}}Id;

    public function __construct(string ${{RESOURCE_VARIABLE}}Id)
    {
        $this->{{RESOURCE_VARIABLE}}Id = ${{RESOURCE_VARIABLE}}Id;
        parent::__construct();
    }

    protected function toArray(): array
    {
        return [
            '{{RESOURCE_VARIABLE}}_id' => $this->{{RESOURCE_VARIABLE}}Id,
        ];
    }
}
PHP;
    }

    private function getGetQueryTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace {{MODULE_NAMESPACE}}\Application\Queries;

use TaiCrm\LaravelModularDdd\Foundation\Query;

class Get{{RESOURCE_NAME}}Query extends Query
{
    private string ${{RESOURCE_VARIABLE}}Id;

    public function __construct(string ${{RESOURCE_VARIABLE}}Id)
    {
        $this->{{RESOURCE_VARIABLE}}Id = ${{RESOURCE_VARIABLE}}Id;
        parent::__construct();
    }

    protected function isCacheable(): bool
    {
        return true;
    }

    protected function getDefaultCacheTtl(): int
    {
        return 300;
    }

    protected function toArray(): array
    {
        return [
            '{{RESOURCE_VARIABLE}}_id' => $this->{{RESOURCE_VARIABLE}}Id,
        ];
    }
}
PHP;
    }

    private function getListQueryTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace {{MODULE_NAMESPACE}}\Application\Queries;

use TaiCrm\LaravelModularDdd\Foundation\Query;

class List{{RESOURCE_NAME}}Query extends Query
{
    private array $filters;
    private string $sort;
    private string $direction;
    private int $perPage;

    public function __construct(
        array $filters = [],
        string $sort = 'created_at',
        string $direction = 'desc',
        int $perPage = 15
    ) {
        $this->filters = $filters;
        $this->sort = $sort;
        $this->direction = $direction;
        $this->perPage = $perPage;
        parent::__construct();
    }

    protected function isCacheable(): bool
    {
        return true;
    }

    protected function getDefaultCacheTtl(): int
    {
        return 60;
    }

    protected function toArray(): array
    {
        return [
            'filters' => $this->filters,
            'sort' => $this->sort,
            'direction' => $this->direction,
            'per_page' => $this->perPage,
        ];
    }
}
PHP;
    }

    private function getCommandHandlerTemplate(string $action): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace {{MODULE_NAMESPACE}}\Application\Handlers\Commands;

use {{MODULE_NAMESPACE}}\Application\Commands\{{ACTION}}{{RESOURCE_NAME}}Command;
use TaiCrm\LaravelModularDdd\Foundation\Contracts\CommandHandlerInterface;
use TaiCrm\LaravelModularDdd\Foundation\Contracts\CommandInterface;

class {{ACTION}}{{RESOURCE_NAME}}CommandHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): mixed
    {
        if (!$command instanceof {{ACTION}}{{RESOURCE_NAME}}Command) {
            throw new \InvalidArgumentException('Expected {{ACTION}}{{RESOURCE_NAME}}Command');
        }

        // TODO: Implement {{ACTION}} {{RESOURCE_NAME}} logic

        return true;
    }
}
PHP;
    }

    private function getQueryHandlerTemplate(string $action): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace {{MODULE_NAMESPACE}}\Application\Handlers\Queries;

use {{MODULE_NAMESPACE}}\Application\Queries\{{ACTION}}{{RESOURCE_NAME}}Query;
use TaiCrm\LaravelModularDdd\Foundation\Contracts\QueryHandlerInterface;
use TaiCrm\LaravelModularDdd\Foundation\Contracts\QueryInterface;

class {{ACTION}}{{RESOURCE_NAME}}QueryHandler implements QueryHandlerInterface
{
    public function handle(QueryInterface $query): mixed
    {
        if (!$query instanceof {{ACTION}}{{RESOURCE_NAME}}Query) {
            throw new \InvalidArgumentException('Expected {{ACTION}}{{RESOURCE_NAME}}Query');
        }

        // TODO: Implement {{ACTION}} {{RESOURCE_NAME}} logic

        return [];
    }
}
PHP;
    }

    private function getSwaggerTemplate(): string
    {
        return <<<'PHP'
<?php

/**
 * @OA\Tag(
 *     name="{{RESOURCE_NAME}}",
 *     description="{{RESOURCE_NAME}} management endpoints"
 * )
 */

/**
 * @OA\Get(
 *     path="/api/{{RESOURCE_KEBAB}}",
 *     tags={"{{RESOURCE_NAME}}"},
 *     summary="List {{RESOURCE_NAME}}s",
 *     @OA\Response(response=200, description="Successful operation")
 * )
 */

/**
 * @OA\Post(
 *     path="/api/{{RESOURCE_KEBAB}}",
 *     tags={"{{RESOURCE_NAME}}"},
 *     summary="Create {{RESOURCE_NAME}}",
 *     @OA\Response(response=201, description="{{RESOURCE_NAME}} created")
 * )
 */

/**
 * @OA\Get(
 *     path="/api/{{RESOURCE_KEBAB}}/{id}",
 *     tags={"{{RESOURCE_NAME}}"},
 *     summary="Get {{RESOURCE_NAME}}",
 *     @OA\Parameter(name="id", in="path", required=true),
 *     @OA\Response(response=200, description="{{RESOURCE_NAME}} found")
 * )
 */

/**
 * @OA\Put(
 *     path="/api/{{RESOURCE_KEBAB}}/{id}",
 *     tags={"{{RESOURCE_NAME}}"},
 *     summary="Update {{RESOURCE_NAME}}",
 *     @OA\Parameter(name="id", in="path", required=true),
 *     @OA\Response(response=200, description="{{RESOURCE_NAME}} updated")
 * )
 */

/**
 * @OA\Delete(
 *     path="/api/{{RESOURCE_KEBAB}}/{id}",
 *     tags={"{{RESOURCE_NAME}}"},
 *     summary="Delete {{RESOURCE_NAME}}",
 *     @OA\Parameter(name="id", in="path", required=true),
 *     @OA\Response(response=200, description="{{RESOURCE_NAME}} deleted")
 * )
 */
PHP;
    }

    private function getValidationImports(string $moduleName, string $resourceName): string
    {
        return "\nuse Modules\\{$moduleName}\\Http\\Requests\\{$resourceName}\\Create{$resourceName}Request;\nuse Modules\\{$moduleName}\\Http\\Requests\\{$resourceName}\\Update{$resourceName}Request;";
    }

    private function getRequestClasses(string $resourceName): string
    {
        return "Create{$resourceName}Request|Update{$resourceName}Request";
    }

    private function getSwaggerAnnotations(string $resourceName, string $apiVersion): string
    {
        return "\n\n/**\n * @OA\Info(title=\"{$resourceName} API\", version=\"{$apiVersion}\")\n */";
    }

    private function getCreateValidationRules(string $resourceName): string
    {
        return "            'name' => 'required|string|max:255',\n            'description' => 'nullable|string',\n            'status' => 'boolean',";
    }

    private function getUpdateValidationRules(string $resourceName): string
    {
        return "            'name' => 'string|max:255',\n            'description' => 'nullable|string',\n            'status' => 'boolean',";
    }

    private function getResourceAttributes(string $resourceName): string
    {
        return "            'id' => \$this->id,\n            'name' => \$this->name,\n            'description' => \$this->description,\n            'status' => \$this->status,\n            'created_at' => \$this->created_at,\n            'updated_at' => \$this->updated_at,";
    }

    private function getCommandProperties(string $resourceName): string
    {
        return "    private string \$name;\n    private ?string \$description;\n    private bool \$status;";
    }

    private function getConstructorParams(string $resourceName): string
    {
        return "string \$name, ?string \$description = null, bool \$status = true";
    }

    private function getConstructorAssignments(string $resourceName): string
    {
        return "        \$this->name = \$name;\n        \$this->description = \$description;\n        \$this->status = \$status;";
    }

    private function getToArrayContent(string $resourceName): string
    {
        return "            'name' => \$this->name,\n            'description' => \$this->description,\n            'status' => \$this->status,";
    }

    private function getUpdateCommandProperties(string $resourceName): string
    {
        $var = Str::camel($resourceName);
        return "    private string \${$var}Id;\n    private array \$data;";
    }

    private function getUpdateConstructorParams(string $resourceName): string
    {
        $var = Str::camel($resourceName);
        return "string \${$var}Id, array \$data";
    }

    private function getUpdateConstructorAssignments(string $resourceName): string
    {
        $var = Str::camel($resourceName);
        return "        \$this->{$var}Id = \${$var}Id;\n        \$this->data = \$data;";
    }

    private function getUpdateToArrayContent(string $resourceName): string
    {
        $var = Str::camel($resourceName);
        $snake = Str::snake($resourceName);
        return "            '{$snake}_id' => \$this->{$var}Id,\n            'data' => \$this->data,";
    }

    /**
     * Get comprehensive Swagger controller template from stub file
     */
    private function getComprehensiveSwaggerControllerTemplate(): string
    {
        $stubPath = __DIR__ . '/../../stubs/api-controller-comprehensive-swagger.stub';

        if (file_exists($stubPath)) {
            return file_get_contents($stubPath);
        }

        // Fallback to inline template if stub file doesn't exist
        return $this->getControllerTemplate();
    }

    /**
     * Get comprehensive Swagger create request template from stub file
     */
    private function getComprehensiveCreateRequestTemplate(): string
    {
        $stubPath = __DIR__ . '/../../stubs/api-request-create-swagger.stub';

        if (file_exists($stubPath)) {
            return file_get_contents($stubPath);
        }

        // Fallback to inline template if stub file doesn't exist
        return $this->getCreateRequestTemplate();
    }

    /**
     * Get comprehensive Swagger update request template from stub file
     */
    private function getComprehensiveUpdateRequestTemplate(): string
    {
        $stubPath = __DIR__ . '/../../stubs/api-request-update-swagger.stub';

        if (file_exists($stubPath)) {
            return file_get_contents($stubPath);
        }

        // Fallback to inline template if stub file doesn't exist
        return $this->getUpdateRequestTemplate();
    }

    /**
     * Get comprehensive Swagger resource template from stub file
     */
    private function getComprehensiveResourceTemplate(): string
    {
        $stubPath = __DIR__ . '/../../stubs/api-resource-swagger.stub';

        if (file_exists($stubPath)) {
            return file_get_contents($stubPath);
        }

        // Fallback to inline template if stub file doesn't exist
        return $this->getResourceTemplate();
    }

    /**
     * Enhanced validation rules for comprehensive requests
     */
    private function getComprehensiveCreateValidationRules(string $resourceName): string
    {
        return "            'name' => 'required|string|max:255|unique:". Str::snake(Str::plural($resourceName)) . ",name',\n" .
               "            'description' => 'nullable|string|max:1000',\n" .
               "            'status' => 'boolean',\n" .
               "            'metadata' => 'nullable|array',\n" .
               "            'metadata.*' => 'string|numeric|boolean',";
    }

    /**
     * Enhanced validation rules for comprehensive update requests
     */
    private function getComprehensiveUpdateValidationRules(string $resourceName): string
    {
        return "            'name' => 'sometimes|string|max:255|unique:". Str::snake(Str::plural($resourceName)) . ",name,' . \$" . Str::camel($resourceName) . "Id,\n" .
               "            'description' => 'nullable|string|max:1000',\n" .
               "            'status' => 'boolean',\n" .
               "            'metadata' => 'nullable|array',\n" .
               "            'metadata.*' => 'string|numeric|boolean',";
    }

    /**
     * Enhanced resource attributes for comprehensive resources
     */
    private function getComprehensiveResourceAttributes(string $resourceName): string
    {
        return "            'id' => \$this->id,\n" .
               "            'name' => \$this->name,\n" .
               "            'description' => \$this->description,\n" .
               "            'status' => \$this->formatStatus(\$this->status ?? true),\n" .
               "            'metadata' => \$this->transformMetadata(\$this->metadata),\n" .
               "            'created_at' => \$this->created_at?->toISOString(),\n" .
               "            'updated_at' => \$this->updated_at?->toISOString(),";
    }
}