<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ModuleMakeControllerCommand extends Command
{
    protected $signature = 'module:make-controller {module} {name} {--api} {--resource=} {--middleware=}';
    protected $description = 'Create a new controller for a module';

    public function handle(): int
    {
        $moduleName = $this->argument('module');
        $controllerName = $this->argument('name');
        $isApi = $this->option('api');
        $resource = $this->option('resource');
        $middleware = $this->option('middleware');

        if (!$this->moduleExists($moduleName)) {
            $this->error("Module '{$moduleName}' does not exist.");

            return 1;
        }

        $this->createController($moduleName, $controllerName, $isApi, $resource, $middleware);

        $this->info("Controller '{$controllerName}' created successfully for module '{$moduleName}'.");

        return 0;
    }

    private function moduleExists(string $moduleName): bool
    {
        return is_dir(base_path("modules/{$moduleName}"));
    }

    private function createController(string $moduleName, string $controllerName, bool $isApi, ?string $resource, ?string $middleware): void
    {
        $controllersDir = base_path("modules/{$moduleName}/Http/Controllers");
        $this->ensureDirectoryExists($controllersDir);

        $controllerFile = "{$controllersDir}/{$controllerName}.php";

        if ($isApi && $resource) {
            $template = $this->getApiResourceControllerTemplate();
        } elseif ($isApi) {
            $template = $this->getApiControllerTemplate();
        } else {
            $template = $this->getWebControllerTemplate();
        }

        $replacements = [
            '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{CONTROLLER_NAME}}' => $controllerName,
            '{{RESOURCE_NAME}}' => $resource ?? 'Resource',
            '{{RESOURCE_NAME_LOWER}}' => $resource ? strtolower($resource) : 'resource',
            '{{RESOURCE_VARIABLE}}' => $resource ? Str::camel($resource) : 'resource',
            '{{RESOURCE_SNAKE}}' => $resource ? Str::snake($resource) : 'resource',
            '{{MIDDLEWARE}}' => $middleware ? "->middleware('{$middleware}')" : '',
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);
        file_put_contents($controllerFile, $content);
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }
    }

    private function getApiResourceControllerTemplate(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace {{MODULE_NAMESPACE}}\Http\Controllers;

            use Illuminate\Http\Request;
            use Illuminate\Http\JsonResponse;
            use App\Http\Controllers\Controller;
            use TaiCrm\LaravelModularDdd\Foundation\CommandBus;
            use TaiCrm\LaravelModularDdd\Foundation\QueryBus;

            class {{CONTROLLER_NAME}} extends Controller
            {
                public function __construct(
                    private CommandBus $commandBus,
                    private QueryBus $queryBus
                ) {}

                /**
                 * Display a listing of the resource.
                 */
                public function index(Request $request): JsonResponse
                {
                    try {
                        $query = new Get{{RESOURCE_NAME}}ListQuery(
                            page: $request->get('page', 1),
                            perPage: $request->get('per_page', 15),
                            search: $request->get('search'),
                            filters: $request->only(['status', 'category']) // Adjust based on your needs
                        );

                        $result = $this->queryBus->ask($query);

                        return response()->json([
                            'data' => {{RESOURCE_NAME}}Resource::collection($result->data),
                            'meta' => [
                                'current_page' => $result->currentPage,
                                'last_page' => $result->lastPage,
                                'per_page' => $result->perPage,
                                'total' => $result->total,
                            ]
                        ]);
                    } catch (\Exception $e) {
                        return response()->json([
                            'error' => 'Failed to retrieve {{RESOURCE_NAME_LOWER}} records',
                            'message' => $e->getMessage()
                        ], 500);
                    }
                }

                /**
                 * Store a newly created resource in storage.
                 */
                public function store(Create{{RESOURCE_NAME}}Request $request): JsonResponse
                {
                    try {
                        $command = new Create{{RESOURCE_NAME}}Command(...$request->validated());
                        ${{RESOURCE_VARIABLE}} = $this->commandBus->dispatch($command);

                        return response()->json([
                            'data' => new {{RESOURCE_NAME}}Resource(${{RESOURCE_VARIABLE}}),
                            'message' => '{{RESOURCE_NAME}} created successfully'
                        ], 201);
                    } catch (\Exception $e) {
                        return response()->json([
                            'error' => 'Failed to create {{RESOURCE_NAME_LOWER}}',
                            'message' => $e->getMessage()
                        ], 400);
                    }
                }

                /**
                 * Display the specified resource.
                 */
                public function show(string $id): JsonResponse
                {
                    try {
                        $query = new Get{{RESOURCE_NAME}}ByIdQuery($id);
                        ${{RESOURCE_VARIABLE}} = $this->queryBus->ask($query);

                        return response()->json([
                            'data' => new {{RESOURCE_NAME}}Resource(${{RESOURCE_VARIABLE}})
                        ]);
                    } catch (\Exception $e) {
                        return response()->json([
                            'error' => '{{RESOURCE_NAME}} not found',
                            'message' => $e->getMessage()
                        ], 404);
                    }
                }

                /**
                 * Update the specified resource in storage.
                 */
                public function update(Update{{RESOURCE_NAME}}Request $request, string $id): JsonResponse
                {
                    try {
                        $command = new Update{{RESOURCE_NAME}}Command($id, ...$request->validated());
                        ${{RESOURCE_VARIABLE}} = $this->commandBus->dispatch($command);

                        return response()->json([
                            'data' => new {{RESOURCE_NAME}}Resource(${{RESOURCE_VARIABLE}}),
                            'message' => '{{RESOURCE_NAME}} updated successfully'
                        ]);
                    } catch (\Exception $e) {
                        return response()->json([
                            'error' => 'Failed to update {{RESOURCE_NAME_LOWER}}',
                            'message' => $e->getMessage()
                        ], 400);
                    }
                }

                /**
                 * Remove the specified resource from storage.
                 */
                public function destroy(string $id): JsonResponse
                {
                    try {
                        $command = new Delete{{RESOURCE_NAME}}Command($id);
                        $this->commandBus->dispatch($command);

                        return response()->json([
                            'message' => '{{RESOURCE_NAME}} deleted successfully'
                        ]);
                    } catch (\Exception $e) {
                        return response()->json([
                            'error' => 'Failed to delete {{RESOURCE_NAME_LOWER}}',
                            'message' => $e->getMessage()
                        ], 400);
                    }
                }
            }
            PHP;
    }

    private function getApiControllerTemplate(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace {{MODULE_NAMESPACE}}\Http\Controllers;

            use Illuminate\Http\Request;
            use Illuminate\Http\JsonResponse;
            use App\Http\Controllers\Controller;
            use TaiCrm\LaravelModularDdd\Foundation\CommandBus;
            use TaiCrm\LaravelModularDdd\Foundation\QueryBus;

            class {{CONTROLLER_NAME}} extends Controller
            {
                public function __construct(
                    private CommandBus $commandBus,
                    private QueryBus $queryBus
                ) {}

                /**
                 * Handle the incoming request.
                 */
                public function __invoke(Request $request): JsonResponse
                {
                    try {
                        // Process the request using appropriate command or query
                        $result = $this->processRequest($request);

                        return response()->json([
                            'data' => $result,
                            'message' => 'Request processed successfully'
                        ]);
                    } catch (\Exception $e) {
                        return response()->json([
                            'error' => 'Request processing failed',
                            'message' => $e->getMessage()
                        ], 500);
                    }
                }

                /**
                 * Process the incoming request.
                 */
                private function processRequest(Request $request): mixed
                {
                    // Implement your business logic here
                    // Example: $query = new GetDataQuery($request->validated());
                    // return $this->queryBus->ask($query);

                    return [];
                }
            }
            PHP;
    }

    private function getWebControllerTemplate(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace {{MODULE_NAMESPACE}}\Http\Controllers;

            use Illuminate\Http\Request;
            use Illuminate\Http\Response;
            use Illuminate\View\View;
            use App\Http\Controllers\Controller;
            use TaiCrm\LaravelModularDdd\Foundation\CommandBus;
            use TaiCrm\LaravelModularDdd\Foundation\QueryBus;

            class {{CONTROLLER_NAME}} extends Controller
            {
                public function __construct(
                    private CommandBus $commandBus,
                    private QueryBus $queryBus
                ) {}

                /**
                 * Handle the incoming request.
                 */
                public function __invoke(Request $request): View|Response
                {
                    try {
                        // Process the request using appropriate command or query
                        $data = $this->processRequest($request);

                        // Return view with data or redirect as needed
                        return view('{{MODULE_NAMESPACE|lower}}::index', compact('data'));
                    } catch (\Exception $e) {
                        // Handle errors gracefully - redirect back with error or show error page
                        return back()->withErrors(['error' => $e->getMessage()]);
                    }
                }

                /**
                 * Process the incoming request.
                 */
                private function processRequest(Request $request): array
                {
                    // Implement your business logic here
                    // Example: $query = new GetDataQuery($request->validated());
                    // return $this->queryBus->ask($query);

                    return [
                        'message' => 'Request processed successfully',
                        'timestamp' => now(),
                    ];
                }
            }
            PHP;
    }
}
