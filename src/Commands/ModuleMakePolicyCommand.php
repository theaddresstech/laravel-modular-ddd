<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ModuleMakePolicyCommand extends Command
{
    protected $signature = 'module:make-policy {module} {name} {--model=} {--resource} {--api}';
    protected $description = 'Create a new authorization policy for a module';

    public function handle(): int
    {
        $moduleName = $this->argument('module');
        $policyName = $this->argument('name');
        $model = $this->option('model');
        $isResource = $this->option('resource');
        $isApi = $this->option('api');

        if (!$this->moduleExists($moduleName)) {
            $this->error("Module '{$moduleName}' does not exist.");
            return 1;
        }

        $this->createPolicy($moduleName, $policyName, $model, $isResource, $isApi);

        $this->info("Policy '{$policyName}' created successfully for module '{$moduleName}'.");

        return 0;
    }

    private function moduleExists(string $moduleName): bool
    {
        return is_dir(base_path("modules/{$moduleName}"));
    }

    private function createPolicy(string $moduleName, string $policyName, ?string $model, bool $isResource, bool $isApi): void
    {
        $policiesDir = base_path("modules/{$moduleName}/Policies");
        $this->ensureDirectoryExists($policiesDir);

        $policyFile = "{$policiesDir}/{$policyName}.php";

        if ($isResource && $model) {
            $template = $this->getResourcePolicyTemplate();
        } elseif ($isResource) {
            $template = $this->getResourcePolicyTemplate();
            $model = str_replace('Policy', '', $policyName);
        } else {
            $template = $this->getBasicPolicyTemplate();
        }

        $replacements = [
            '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{POLICY_NAME}}' => $policyName,
            '{{MODEL_NAME}}' => $model ?? 'Model',
            '{{MODEL_VARIABLE}}' => $model ? Str::camel($model) : 'model',
            '{{MODULE_ID}}' => Str::kebab($moduleName),
            '{{PERMISSIONS}}' => $this->generatePermissionMethods($model, $isResource, $isApi),
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);
        file_put_contents($policyFile, $content);
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function getBasicPolicyTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace {{MODULE_NAMESPACE}}\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

class {{POLICY_NAME}}
{
    use HandlesAuthorization;

{{PERMISSIONS}}
}
PHP;
    }

    private function getResourcePolicyTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace {{MODULE_NAMESPACE}}\Policies;

use {{MODULE_NAMESPACE}}\Domain\Entities\{{MODEL_NAME}};
use Illuminate\Auth\Access\HandlesAuthorization;

class {{POLICY_NAME}}
{
    use HandlesAuthorization;

{{PERMISSIONS}}

    /**
     * Determine if the user can perform any actions on the model.
     */
    public function before($user, $ability): ?bool
    {
        // Super admin check
        if ($user->hasModuleRole('{{MODULE_ID}}', 'admin')) {
            return true;
        }

        return null; // Continue with normal authorization
    }
}
PHP;
    }

    private function generatePermissionMethods(?string $model, bool $isResource, bool $isApi): string
    {
        $methods = [];

        if ($isResource) {
            $modelVar = $model ? Str::camel($model) : 'model';
            $modelClass = $model ?? 'Model';

            $methods[] = $this->generateViewAnyMethod($modelClass);
            $methods[] = $this->generateViewMethod($modelClass, $modelVar);
            $methods[] = $this->generateCreateMethod($modelClass);
            $methods[] = $this->generateUpdateMethod($modelClass, $modelVar);
            $methods[] = $this->generateDeleteMethod($modelClass, $modelVar);

            if (!$isApi) {
                $methods[] = $this->generateRestoreMethod($modelClass, $modelVar);
                $methods[] = $this->generateForceDeleteMethod($modelClass, $modelVar);
            }
        } else {
            $methods[] = $this->generateBasicMethod();
        }

        return implode("\n\n", $methods);
    }

    private function generateViewAnyMethod(string $modelClass): string
    {
        return <<<PHP
    /**
     * Determine whether the user can view any {$modelClass} models.
     */
    public function viewAny(\$user): bool
    {
        return \$user->hasModulePermission('{{MODULE_ID}}', 'view-any-{{MODEL_VARIABLE}}');
    }
PHP;
    }

    private function generateViewMethod(string $modelClass, string $modelVar): string
    {
        return <<<PHP
    /**
     * Determine whether the user can view the {$modelClass} model.
     */
    public function view(\$user, {$modelClass} \${$modelVar}): bool
    {
        // User can view if they have view permission
        if (\$user->hasModulePermission('{{MODULE_ID}}', 'view-{{MODEL_VARIABLE}}')) {
            return true;
        }

        // Or if they own the resource
        return \$user->id === \${$modelVar}->user_id;
    }
PHP;
    }

    private function generateCreateMethod(string $modelClass): string
    {
        return <<<PHP
    /**
     * Determine whether the user can create {$modelClass} models.
     */
    public function create(\$user): bool
    {
        return \$user->hasModulePermission('{{MODULE_ID}}', 'create-{{MODEL_VARIABLE}}');
    }
PHP;
    }

    private function generateUpdateMethod(string $modelClass, string $modelVar): string
    {
        return <<<PHP
    /**
     * Determine whether the user can update the {$modelClass} model.
     */
    public function update(\$user, {$modelClass} \${$modelVar}): bool
    {
        // User can update if they have update permission
        if (\$user->hasModulePermission('{{MODULE_ID}}', 'update-{{MODEL_VARIABLE}}')) {
            return true;
        }

        // Or if they own the resource and have edit-own permission
        return \$user->id === \${$modelVar}->user_id &&
               \$user->hasModulePermission('{{MODULE_ID}}', 'edit-own-{{MODEL_VARIABLE}}');
    }
PHP;
    }

    private function generateDeleteMethod(string $modelClass, string $modelVar): string
    {
        return <<<PHP
    /**
     * Determine whether the user can delete the {$modelClass} model.
     */
    public function delete(\$user, {$modelClass} \${$modelVar}): bool
    {
        // User can delete if they have delete permission
        if (\$user->hasModulePermission('{{MODULE_ID}}', 'delete-{{MODEL_VARIABLE}}')) {
            return true;
        }

        // Or if they own the resource and have delete-own permission
        return \$user->id === \${$modelVar}->user_id &&
               \$user->hasModulePermission('{{MODULE_ID}}', 'delete-own-{{MODEL_VARIABLE}}');
    }
PHP;
    }

    private function generateRestoreMethod(string $modelClass, string $modelVar): string
    {
        return <<<PHP
    /**
     * Determine whether the user can restore the {$modelClass} model.
     */
    public function restore(\$user, {$modelClass} \${$modelVar}): bool
    {
        return \$user->hasModulePermission('{{MODULE_ID}}', 'restore-{{MODEL_VARIABLE}}');
    }
PHP;
    }

    private function generateForceDeleteMethod(string $modelClass, string $modelVar): string
    {
        return <<<PHP
    /**
     * Determine whether the user can permanently delete the {$modelClass} model.
     */
    public function forceDelete(\$user, {$modelClass} \${$modelVar}): bool
    {
        return \$user->hasModulePermission('{{MODULE_ID}}', 'force-delete-{{MODEL_VARIABLE}}');
    }
PHP;
    }

    private function generateBasicMethod(): string
    {
        return <<<'PHP'
    /**
     * Determine whether the user can perform this action.
     */
    public function authorize($user): bool
    {
        // TODO: Implement authorization logic
        return $user->hasModulePermission('{{MODULE_ID}}', 'basic-permission');
    }
PHP;
    }
}