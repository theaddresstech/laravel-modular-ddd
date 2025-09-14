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
            mkdir($directory, 0755, true);
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
        // TODO: Implement index logic
        return response()->json([
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 15,
                'total' => 0,
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        // TODO: Implement store logic
        return response()->json([
            'data' => [],
            'message' => '{{RESOURCE_NAME}} created successfully'
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        // TODO: Implement show logic
        return response()->json([
            'data' => []
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        // TODO: Implement update logic
        return response()->json([
            'data' => [],
            'message' => '{{RESOURCE_NAME}} updated successfully'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        // TODO: Implement destroy logic
        return response()->json([
            'message' => '{{RESOURCE_NAME}} deleted successfully'
        ]);
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
        // TODO: Implement controller logic
        return response()->json([
            'message' => 'Success'
        ]);
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
        // TODO: Implement controller logic
        return response('Success');
    }
}
PHP;
    }
}