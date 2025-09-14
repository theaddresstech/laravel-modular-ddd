<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ModuleMakeResourceCommand extends Command
{
    protected $signature = 'module:make-resource {module} {name} {--collection} {--model=}';
    protected $description = 'Create a new API resource for a module';

    public function handle(): int
    {
        $moduleName = $this->argument('module');
        $resourceName = $this->argument('name');
        $isCollection = $this->option('collection');
        $model = $this->option('model');

        if (!$this->moduleExists($moduleName)) {
            $this->error("Module '{$moduleName}' does not exist.");
            return 1;
        }

        $this->createResource($moduleName, $resourceName, $isCollection, $model);

        $type = $isCollection ? 'Resource Collection' : 'Resource';
        $this->info("{$type} '{$resourceName}' created successfully for module '{$moduleName}'.");

        return 0;
    }

    private function moduleExists(string $moduleName): bool
    {
        return is_dir(base_path("modules/{$moduleName}"));
    }

    private function createResource(string $moduleName, string $resourceName, bool $isCollection, ?string $model): void
    {
        $resourcesDir = base_path("modules/{$moduleName}/Http/Resources");
        $this->ensureDirectoryExists($resourcesDir);

        $resourceFile = "{$resourcesDir}/{$resourceName}.php";

        if ($isCollection) {
            $template = $this->getCollectionResourceTemplate();
        } else {
            $template = $this->getResourceTemplate();
        }

        $replacements = [
            '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{RESOURCE_NAME}}' => $resourceName,
            '{{MODEL_NAME}}' => $model ?? 'Model',
            '{{MODEL_VARIABLE}}' => $model ? Str::camel($model) : 'model',
            '{{RESOURCE_ATTRIBUTES}}' => $this->getResourceAttributes($model, $isCollection),
            '{{COLLECTION_WRAPPER}}' => $this->getCollectionWrapper($resourceName),
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);
        file_put_contents($resourceFile, $content);
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function getResourceTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace {{MODULE_NAMESPACE}}\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class {{RESOURCE_NAME}} extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
{{RESOURCE_ATTRIBUTES}}
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0.0',
                'timestamp' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     */
    public function withResponse(Request $request, $response): void
    {
        // Add custom headers or modify response
        $response->header('X-Resource-Type', '{{RESOURCE_NAME}}');
    }
}
PHP;
    }

    private function getCollectionResourceTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace {{MODULE_NAMESPACE}}\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class {{RESOURCE_NAME}} extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
{{COLLECTION_WRAPPER}}
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0.0',
                'timestamp' => now()->toISOString(),
                'total' => $this->collection->count(),
            ],
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     */
    public function withResponse(Request $request, $response): void
    {
        $response->header('X-Resource-Type', '{{RESOURCE_NAME}}Collection');
        $response->header('X-Total-Count', $this->collection->count());
    }
}
PHP;
    }

    private function getResourceAttributes(?string $model, bool $isCollection): string
    {
        if ($isCollection) {
            return '';
        }

        $attributes = [
            "'id' => \$this->id",
            "'created_at' => \$this->created_at",
            "'updated_at' => \$this->updated_at",
        ];

        // Generate common attributes based on model name
        if ($model) {
            $modelLower = strtolower($model);

            if (Str::contains($modelLower, 'user')) {
                $attributes = array_merge([
                    "'id' => \$this->id",
                    "'name' => \$this->name",
                    "'email' => \$this->email",
                    "'email_verified_at' => \$this->email_verified_at",
                    "'avatar' => \$this->avatar",
                ], $attributes);
            } elseif (Str::contains($modelLower, 'post')) {
                $attributes = array_merge([
                    "'id' => \$this->id",
                    "'title' => \$this->title",
                    "'slug' => \$this->slug",
                    "'content' => \$this->content",
                    "'excerpt' => \$this->excerpt",
                    "'status' => \$this->status",
                    "'published_at' => \$this->published_at",
                ], $attributes);
            } elseif (Str::contains($modelLower, 'product')) {
                $attributes = array_merge([
                    "'id' => \$this->id",
                    "'name' => \$this->name",
                    "'slug' => \$this->slug",
                    "'description' => \$this->description",
                    "'price' => \$this->price",
                    "'sku' => \$this->sku",
                    "'stock' => \$this->stock",
                    "'status' => \$this->status",
                ], $attributes);
            } else {
                $attributes = array_merge([
                    "'id' => \$this->id",
                    "'name' => \$this->name",
                    "'description' => \$this->description",
                    "'status' => \$this->status",
                ], $attributes);
            }
        } else {
            $attributes = array_merge([
                "'id' => \$this->id",
                "'name' => \$this->name",
                "'description' => \$this->description",
            ], $attributes);
        }

        // Add conditional relationships
        $attributes[] = "'relationships' => [
                'when' => \$this->whenLoaded('relationships', [
                    // Add related resources here
                ]),
            ]";

        // Add permissions
        $attributes[] = "'permissions' => [
                'can_edit' => \$this->when(auth()->check(), function () {
                    return auth()->user()->can('update', \$this->resource);
                }),
                'can_delete' => \$this->when(auth()->check(), function () {
                    return auth()->user()->can('delete', \$this->resource);
                }),
            ]";

        return "            " . implode(",\n            ", $attributes);
    }

    private function getCollectionWrapper(string $resourceName): string
    {
        return "            'links' => [
                'self' => request()->url(),
                'first' => \$this->url(1),
                'last' => \$this->url(\$this->lastPage()),
                'prev' => \$this->previousPageUrl(),
                'next' => \$this->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => \$this->currentPage(),
                'from' => \$this->firstItem(),
                'last_page' => \$this->lastPage(),
                'per_page' => \$this->perPage(),
                'to' => \$this->lastItem(),
                'total' => \$this->total(),
            ]";
    }
}