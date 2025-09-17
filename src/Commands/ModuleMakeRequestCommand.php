<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ModuleMakeRequestCommand extends Command
{
    protected $signature = 'module:make-request {module} {name} {--resource=} {--validation}';
    protected $description = 'Create a new form request for a module';

    public function handle(): int
    {
        $moduleName = $this->argument('module');
        $requestName = $this->argument('name');
        $resource = $this->option('resource');
        $withValidation = $this->option('validation');

        if (!$this->moduleExists($moduleName)) {
            $this->error("Module '{$moduleName}' does not exist.");

            return 1;
        }

        $this->createRequest($moduleName, $requestName, $resource, $withValidation);

        $this->info("Request '{$requestName}' created successfully for module '{$moduleName}'.");

        return 0;
    }

    private function moduleExists(string $moduleName): bool
    {
        return is_dir(base_path("modules/{$moduleName}"));
    }

    private function createRequest(string $moduleName, string $requestName, ?string $resource, bool $withValidation): void
    {
        $requestsDir = base_path("modules/{$moduleName}/Http/Requests");

        if ($resource) {
            $requestsDir .= "/{$resource}";
        }

        $this->ensureDirectoryExists($requestsDir);

        $requestFile = "{$requestsDir}/{$requestName}.php";
        $template = $this->getRequestTemplate();

        $replacements = [
            '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{REQUEST_NAME}}' => $requestName,
            '{{RESOURCE_NAME}}' => $resource ?? 'Resource',
            '{{RESOURCE_PATH}}' => $resource ? "\\{$resource}" : '',
            '{{VALIDATION_RULES}}' => $withValidation ? $this->getValidationRules($requestName, $resource) : 'return [];',
            '{{VALIDATION_MESSAGES}}' => $withValidation ? $this->getValidationMessages($requestName, $resource) : 'return [];',
            '{{AUTHORIZATION_LOGIC}}' => $this->getAuthorizationLogic($requestName),
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);
        file_put_contents($requestFile, $content);
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }
    }

    private function getRequestTemplate(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace {{MODULE_NAMESPACE}}\Http\Requests{{RESOURCE_PATH}};

            use Illuminate\Foundation\Http\FormRequest;

            class {{REQUEST_NAME}} extends FormRequest
            {
                /**
                 * Determine if the user is authorized to make this request.
                 */
                public function authorize(): bool
                {
                    {{AUTHORIZATION_LOGIC}}
                }

                /**
                 * Get the validation rules that apply to the request.
                 */
                public function rules(): array
                {
                    {{VALIDATION_RULES}}
                }

                /**
                 * Get custom messages for validator errors.
                 */
                public function messages(): array
                {
                    {{VALIDATION_MESSAGES}}
                }

                /**
                 * Get custom attributes for validator errors.
                 */
                public function attributes(): array
                {
                    return [
                        // 'field_name' => 'Human Readable Field Name',
                    ];
                }

                /**
                 * Configure the validator instance.
                 */
                public function withValidator($validator): void
                {
                    $validator->after(function ($validator) {
                        // Add custom validation logic here
                    });
                }

                /**
                 * Prepare the data for validation.
                 */
                protected function prepareForValidation(): void
                {
                    // Transform data before validation
                    // Example: $this->merge(['slug' => Str::slug($this->title)]);
                }
            }
            PHP;
    }

    private function getValidationRules(string $requestName, ?string $resource): string
    {
        $rules = [];

        // Generate basic rules based on request name
        if (Str::contains(strtolower($requestName), 'create')) {
            $rules = [
                "'name' => 'required|string|max:255'",
                "'email' => 'required|email|unique:users,email'",
                "'description' => 'nullable|string'",
                "'status' => 'boolean'",
            ];
        } elseif (Str::contains(strtolower($requestName), 'update')) {
            $rules = [
                "'name' => 'sometimes|required|string|max:255'",
                "'email' => 'sometimes|required|email|unique:users,email,' . \$this->route('id')",
                "'description' => 'nullable|string'",
                "'status' => 'boolean'",
            ];
        } elseif (Str::contains(strtolower($requestName), 'login')) {
            $rules = [
                "'email' => 'required|email'",
                "'password' => 'required|string|min:6'",
                "'remember_me' => 'boolean'",
            ];
        } elseif (Str::contains(strtolower($requestName), 'register')) {
            $rules = [
                "'name' => 'required|string|max:255'",
                "'email' => 'required|email|unique:users,email'",
                "'password' => 'required|string|min:8|confirmed'",
                "'terms' => 'accepted'",
            ];
        } else {
            $rules = [
                "'name' => 'required|string|max:255'",
                "'description' => 'nullable|string'",
            ];
        }

        return "return [\n            " . implode(",\n            ", $rules) . ",\n        ];";
    }

    private function getValidationMessages(string $requestName, ?string $resource): string
    {
        $messages = [];

        if (Str::contains(strtolower($requestName), ['create', 'update'])) {
            $messages = [
                "'name.required' => 'The name field is required.'",
                "'name.string' => 'The name must be a string.'",
                "'name.max' => 'The name may not be greater than :max characters.'",
                "'email.required' => 'The email field is required.'",
                "'email.email' => 'The email must be a valid email address.'",
                "'email.unique' => 'The email has already been taken.'",
            ];
        } elseif (Str::contains(strtolower($requestName), 'login')) {
            $messages = [
                "'email.required' => 'Please enter your email address.'",
                "'email.email' => 'Please enter a valid email address.'",
                "'password.required' => 'Please enter your password.'",
                "'password.min' => 'Password must be at least :min characters.'",
            ];
        } elseif (Str::contains(strtolower($requestName), 'register')) {
            $messages = [
                "'name.required' => 'Please enter your full name.'",
                "'email.required' => 'Please enter your email address.'",
                "'email.unique' => 'This email address is already registered.'",
                "'password.required' => 'Please create a password.'",
                "'password.min' => 'Password must be at least :min characters.'",
                "'password.confirmed' => 'Password confirmation does not match.'",
                "'terms.accepted' => 'You must accept the terms and conditions.'",
            ];
        } else {
            $messages = [
                "'name.required' => 'The name field is required.'",
                "'name.string' => 'The name must be a string.'",
            ];
        }

        if (empty($messages)) {
            return 'return [];';
        }

        return "return [\n            " . implode(",\n            ", $messages) . ",\n        ];";
    }

    private function getAuthorizationLogic(string $requestName): string
    {
        if (Str::contains(strtolower($requestName), ['create', 'store'])) {
            return "// Check if user can create this resource\n        return auth()->check();";
        }
        if (Str::contains(strtolower($requestName), ['update', 'edit'])) {
            return "// Check if user can update this resource\n        return auth()->check() && auth()->user()->can('update', \$this->route('model'));";
        }
        if (Str::contains(strtolower($requestName), ['delete', 'destroy'])) {
            return "// Check if user can delete this resource\n        return auth()->check() && auth()->user()->can('delete', \$this->route('model'));";
        }

        return 'return true;';
    }
}
