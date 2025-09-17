<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ModuleMakeRuleCommand extends Command
{
    protected $signature = 'module:make-rule {module} {name}';
    protected $description = 'Create a new validation rule for a module';

    public function __construct(
        private Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $moduleName = $this->argument('module');
        $ruleName = $this->argument('name');

        if (!$this->moduleExists($moduleName)) {
            $this->error("Module '{$moduleName}' does not exist.");

            return 1;
        }

        // Ensure rule name is in StudlyCase
        $ruleClassName = Str::studly($ruleName);

        $this->createRule($moduleName, $ruleClassName);

        $this->info("Validation rule '{$ruleClassName}' created successfully for module '{$moduleName}'.");
        $this->line("Location: modules/{$moduleName}/Http/Rules/{$ruleClassName}.php");

        return 0;
    }

    private function moduleExists(string $moduleName): bool
    {
        return is_dir(base_path("modules/{$moduleName}"));
    }

    private function createRule(string $moduleName, string $ruleClassName): void
    {
        $rulesDir = base_path("modules/{$moduleName}/Http/Rules");
        $this->ensureDirectoryExists($rulesDir);

        $ruleFile = "{$rulesDir}/{$ruleClassName}.php";

        // Check if stub exists, otherwise use built-in template
        $stubPath = $this->getStubPath();
        if ($this->files->exists($stubPath)) {
            $template = $this->files->get($stubPath);
        } else {
            $template = $this->getRuleTemplate();
        }

        $replacements = [
            '{{ class }}' => $ruleClassName,
            '{{class}}' => $ruleClassName,
            '{{ module }}' => $moduleName,
            '{{module}}' => $moduleName,
            '{{ rule_name }}' => Str::snake($ruleClassName),
            '{{rule_name}}' => Str::snake($ruleClassName),
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);
        $this->files->put($ruleFile, $content);
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!$this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0o755, true);
        }
    }

    private function getStubPath(): string
    {
        return __DIR__ . '/../../stubs/rule.stub';
    }

    private function getRuleTemplate(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace Modules\{{ module }}\Http\Rules;

            use Illuminate\Contracts\Validation\Rule;

            class {{ class }} implements Rule
            {
                /**
                 * Create a new rule instance.
                 */
                public function __construct()
                {
                    // Initialize any dependencies or configuration here
                }

                /**
                 * Determine if the validation rule passes.
                 *
                 * @param  string  $attribute
                 * @param  mixed  $value
                 * @return bool
                 */
                public function passes($attribute, $value): bool
                {
                    // Implement your validation logic here
                    // Return true if validation passes, false otherwise

                    // Example: Check if value is uppercase
                    // return $value === strtoupper($value);

                    return false; // TODO: Implement validation logic
                }

                /**
                 * Get the validation error message.
                 */
                public function message(): string
                {
                    return 'The :attribute field must pass the {{ rule_name }} validation rule.';
                }
            }
            PHP;
    }
}
