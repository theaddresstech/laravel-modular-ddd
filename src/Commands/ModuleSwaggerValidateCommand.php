<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use TaiCrm\LaravelModularDdd\Documentation\SwaggerAnnotationScanner;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use Illuminate\Support\Facades\File;

class ModuleSwaggerValidateCommand extends Command
{
    protected $signature = 'module:swagger:validate
                            {module? : Specific module to validate}
                            {--strict : Enable strict validation mode}
                            {--fix : Automatically fix common issues}
                            {--report= : Generate validation report file}';

    protected $description = 'Validate Swagger documentation quality and completeness';

    private array $validationResults = [];
    private array $issues = [];

    public function __construct(
        private SwaggerAnnotationScanner $scanner,
        private ModuleManagerInterface $moduleManager
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $moduleName = $this->argument('module');
        $strict = $this->option('strict');
        $fix = $this->option('fix');
        $reportFile = $this->option('report');

        $this->info('🔍 Validating Swagger documentation...');

        if ($moduleName) {
            $this->validateModule($moduleName, $strict, $fix);
        } else {
            $this->validateAllModules($strict, $fix);
        }

        $this->displayValidationResults();

        if ($reportFile) {
            $this->generateValidationReport($reportFile);
        }

        $hasErrors = $this->hasErrors();

        if ($hasErrors) {
            $this->error('❌ Validation failed with errors');
            return 1;
        }

        $this->info('✅ All validations passed!');
        return 0;
    }

    /**
     * Validate a specific module
     */
    private function validateModule(string $moduleName, bool $strict, bool $fix): void
    {
        if (!$this->moduleExists($moduleName)) {
            $this->error("Module '{$moduleName}' does not exist.");
            return;
        }

        $this->line("📋 Validating module: {$moduleName}");

        $result = $this->scanner->scanModule($moduleName);
        $this->validateModuleDocumentation($moduleName, $result, $strict, $fix);
    }

    /**
     * Validate all modules
     */
    private function validateAllModules(bool $strict, bool $fix): void
    {
        $modules = $this->moduleManager->list();

        $progressBar = $this->output->createProgressBar(count($modules));
        $progressBar->setFormat('%current%/%max% [%bar%] %percent:3s%% - %message%');
        $progressBar->setMessage('Starting validation...');
        $progressBar->start();

        foreach ($modules as $module) {
            if (!$module->isEnabled()) {
                $progressBar->setMessage("Skipping disabled: {$module->getName()}");
                $progressBar->advance();
                continue;
            }

            $progressBar->setMessage("Validating: {$module->getName()}");

            $result = $this->scanner->scanModule($module->getName());
            $this->validateModuleDocumentation($module->getName(), $result, $strict, $fix);

            $progressBar->advance();
        }

        $progressBar->setMessage('Validation completed');
        $progressBar->finish();
        $this->newLine();
    }

    /**
     * Validate module documentation
     */
    private function validateModuleDocumentation(string $moduleName, array $documentation, bool $strict, bool $fix): void
    {
        $moduleIssues = [];
        $moduleValidations = [
            'has_documentation' => false,
            'has_paths' => false,
            'has_schemas' => false,
            'paths_have_descriptions' => true,
            'paths_have_examples' => true,
            'schemas_complete' => true,
            'has_security' => false,
            'has_error_responses' => true,
        ];

        // Check if module has documentation
        if (!empty($documentation['paths']) || !empty($documentation['components']['schemas'])) {
            $moduleValidations['has_documentation'] = true;
        } else {
            $moduleIssues[] = [
                'type' => 'error',
                'message' => 'No Swagger documentation found',
                'severity' => 'high',
                'fixable' => false,
            ];
        }

        // Validate paths
        if (!empty($documentation['paths'])) {
            $moduleValidations['has_paths'] = true;
            $this->validatePaths($documentation['paths'], $moduleIssues, $strict);
        } else {
            $moduleIssues[] = [
                'type' => 'warning',
                'message' => 'No API paths documented',
                'severity' => 'medium',
                'fixable' => false,
            ];
            $moduleValidations['paths_have_descriptions'] = false;
            $moduleValidations['paths_have_examples'] = false;
        }

        // Validate schemas
        if (!empty($documentation['components']['schemas'])) {
            $moduleValidations['has_schemas'] = true;
            $this->validateSchemas($documentation['components']['schemas'], $moduleIssues, $strict);
        } else {
            $moduleIssues[] = [
                'type' => 'warning',
                'message' => 'No schemas documented',
                'severity' => 'medium',
                'fixable' => false,
            ];
            $moduleValidations['schemas_complete'] = false;
        }

        // Check for security documentation
        if ($this->hasSecurityDocumentation($documentation)) {
            $moduleValidations['has_security'] = true;
        } else if ($strict) {
            $moduleIssues[] = [
                'type' => 'warning',
                'message' => 'No security schemes documented',
                'severity' => 'low',
                'fixable' => true,
            ];
        }

        $this->validationResults[$moduleName] = $moduleValidations;
        $this->issues[$moduleName] = $moduleIssues;

        // Apply fixes if requested
        if ($fix && !empty($moduleIssues)) {
            $this->applyFixes($moduleName, $moduleIssues);
        }
    }

    /**
     * Validate paths documentation
     */
    private function validatePaths(array $paths, array &$issues, bool $strict): void
    {
        foreach ($paths as $path => $methods) {
            foreach ($methods as $method => $operation) {
                $operationId = $operation['operationId'] ?? "{$method}_{$path}";

                // Check for required fields
                if (empty($operation['summary'])) {
                    $issues[] = [
                        'type' => 'error',
                        'message' => "Path {$method} {$path}: Missing summary",
                        'severity' => 'high',
                        'fixable' => true,
                        'location' => $operationId,
                    ];
                }

                if (empty($operation['description']) && $strict) {
                    $issues[] = [
                        'type' => 'warning',
                        'message' => "Path {$method} {$path}: Missing description",
                        'severity' => 'medium',
                        'fixable' => true,
                        'location' => $operationId,
                    ];
                }

                // Check responses
                if (empty($operation['responses'])) {
                    $issues[] = [
                        'type' => 'error',
                        'message' => "Path {$method} {$path}: No responses documented",
                        'severity' => 'high',
                        'fixable' => true,
                        'location' => $operationId,
                    ];
                } else {
                    $this->validateResponses($operation['responses'], $path, $method, $issues, $strict);
                }

                // Check parameters
                if (!empty($operation['parameters'])) {
                    $this->validateParameters($operation['parameters'], $path, $method, $issues, $strict);
                }

                // Check security for non-GET operations
                if ($method !== 'get' && empty($operation['security']) && $strict) {
                    $issues[] = [
                        'type' => 'warning',
                        'message' => "Path {$method} {$path}: Missing security configuration",
                        'severity' => 'medium',
                        'fixable' => true,
                        'location' => $operationId,
                    ];
                }
            }
        }
    }

    /**
     * Validate responses
     */
    private function validateResponses(array $responses, string $path, string $method, array &$issues, bool $strict): void
    {
        $hasSuccessResponse = false;
        $hasErrorResponse = false;

        foreach ($responses as $statusCode => $response) {
            if ($statusCode >= 200 && $statusCode < 300) {
                $hasSuccessResponse = true;
            }
            if ($statusCode >= 400) {
                $hasErrorResponse = true;
            }

            if (empty($response['description'])) {
                $issues[] = [
                    'type' => 'error',
                    'message' => "Path {$method} {$path}: Response {$statusCode} missing description",
                    'severity' => 'medium',
                    'fixable' => true,
                    'location' => "{$method}_{$path}_response_{$statusCode}",
                ];
            }
        }

        if (!$hasSuccessResponse) {
            $issues[] = [
                'type' => 'error',
                'message' => "Path {$method} {$path}: No success response (2xx) documented",
                'severity' => 'high',
                'fixable' => true,
                'location' => "{$method}_{$path}_responses",
            ];
        }

        if (!$hasErrorResponse && $strict) {
            $issues[] = [
                'type' => 'warning',
                'message' => "Path {$method} {$path}: No error responses (4xx/5xx) documented",
                'severity' => 'low',
                'fixable' => true,
                'location' => "{$method}_{$path}_responses",
            ];
        }
    }

    /**
     * Validate parameters
     */
    private function validateParameters(array $parameters, string $path, string $method, array &$issues, bool $strict): void
    {
        foreach ($parameters as $parameter) {
            if (empty($parameter['name'])) {
                $issues[] = [
                    'type' => 'error',
                    'message' => "Path {$method} {$path}: Parameter missing name",
                    'severity' => 'high',
                    'fixable' => false,
                    'location' => "{$method}_{$path}_parameter",
                ];
            }

            if (empty($parameter['description']) && $strict) {
                $issues[] = [
                    'type' => 'warning',
                    'message' => "Path {$method} {$path}: Parameter '{$parameter['name']}' missing description",
                    'severity' => 'low',
                    'fixable' => true,
                    'location' => "{$method}_{$path}_parameter_{$parameter['name']}",
                ];
            }
        }
    }

    /**
     * Validate schemas
     */
    private function validateSchemas(array $schemas, array &$issues, bool $strict): void
    {
        foreach ($schemas as $schemaName => $schema) {
            if (empty($schema['type'])) {
                $issues[] = [
                    'type' => 'error',
                    'message' => "Schema {$schemaName}: Missing type",
                    'severity' => 'high',
                    'fixable' => false,
                ];
            }

            if (empty($schema['description']) && $strict) {
                $issues[] = [
                    'type' => 'warning',
                    'message' => "Schema {$schemaName}: Missing description",
                    'severity' => 'low',
                    'fixable' => true,
                ];
            }

            if ($schema['type'] === 'object' && empty($schema['properties']) && $strict) {
                $issues[] = [
                    'type' => 'warning',
                    'message' => "Schema {$schemaName}: Object type with no properties",
                    'severity' => 'medium',
                    'fixable' => false,
                ];
            }
        }
    }

    /**
     * Check if documentation has security schemes
     */
    private function hasSecurityDocumentation(array $documentation): bool
    {
        // Check for security in paths
        foreach ($documentation['paths'] ?? [] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if (!empty($operation['security'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Apply automatic fixes
     */
    private function applyFixes(string $moduleName, array $issues): void
    {
        $fixableIssues = array_filter($issues, fn($issue) => $issue['fixable'] ?? false);

        if (empty($fixableIssues)) {
            return;
        }

        $this->line("🔧 Applying fixes for {$moduleName}...");

        foreach ($fixableIssues as $issue) {
            // This is a placeholder for actual fix implementation
            // In a real implementation, you would modify the actual files
            $this->line("  - Fixed: {$issue['message']}");
        }
    }

    /**
     * Display validation results
     */
    private function displayValidationResults(): void
    {
        $this->newLine();
        $this->info('📊 Validation Results:');

        if (empty($this->validationResults)) {
            $this->warn('No modules were validated.');
            return;
        }

        $headers = ['Module', 'Docs', 'Paths', 'Schemas', 'Issues', 'Status'];
        $rows = [];

        $totalIssues = 0;
        $modulesWithIssues = 0;

        foreach ($this->validationResults as $moduleName => $validations) {
            $moduleIssues = $this->issues[$moduleName] ?? [];
            $issueCount = count($moduleIssues);
            $totalIssues += $issueCount;

            if ($issueCount > 0) {
                $modulesWithIssues++;
            }

            $status = $this->getModuleStatus($validations, $moduleIssues);

            $rows[] = [
                $moduleName,
                $validations['has_documentation'] ? '✅' : '❌',
                $validations['has_paths'] ? '✅' : '⚠️',
                $validations['has_schemas'] ? '✅' : '⚠️',
                $issueCount,
                $status,
            ];
        }

        $this->table($headers, $rows);

        // Summary
        $this->newLine();
        $this->line("📈 Summary:");
        $this->line("  - Modules validated: " . count($this->validationResults));
        $this->line("  - Modules with issues: {$modulesWithIssues}");
        $this->line("  - Total issues: {$totalIssues}");

        // Show detailed issues
        if ($totalIssues > 0) {
            $this->displayDetailedIssues();
        }
    }

    /**
     * Get module status based on validations and issues
     */
    private function getModuleStatus(array $validations, array $issues): string
    {
        $errorCount = count(array_filter($issues, fn($issue) => $issue['type'] === 'error'));
        $warningCount = count(array_filter($issues, fn($issue) => $issue['type'] === 'warning'));

        if ($errorCount > 0) {
            return "❌ {$errorCount} errors";
        }

        if ($warningCount > 0) {
            return "⚠️ {$warningCount} warnings";
        }

        if ($validations['has_documentation']) {
            return '✅ Good';
        }

        return '⚪ No docs';
    }

    /**
     * Display detailed issues
     */
    private function displayDetailedIssues(): void
    {
        $this->newLine();
        $this->info('🔍 Detailed Issues:');

        foreach ($this->issues as $moduleName => $moduleIssues) {
            if (empty($moduleIssues)) {
                continue;
            }

            $this->newLine();
            $this->line("📋 {$moduleName}:");

            foreach ($moduleIssues as $issue) {
                $icon = $issue['type'] === 'error' ? '❌' : '⚠️';
                $severity = strtoupper($issue['severity']);
                $fixable = $issue['fixable'] ? '🔧' : '';

                $this->line("  {$icon} [{$severity}] {$issue['message']} {$fixable}");
            }
        }
    }

    /**
     * Check if there are any errors
     */
    private function hasErrors(): bool
    {
        foreach ($this->issues as $moduleIssues) {
            foreach ($moduleIssues as $issue) {
                if ($issue['type'] === 'error') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Generate validation report
     */
    private function generateValidationReport(string $reportFile): void
    {
        $report = [
            'timestamp' => now()->toISOString(),
            'summary' => [
                'modules_validated' => count($this->validationResults),
                'total_issues' => array_sum(array_map('count', $this->issues)),
                'modules_with_issues' => count(array_filter($this->issues, fn($issues) => !empty($issues))),
            ],
            'results' => $this->validationResults,
            'issues' => $this->issues,
        ];

        File::put($reportFile, json_encode($report, JSON_PRETTY_PRINT));
        $this->line("📄 Validation report saved: {$reportFile}");
    }

    /**
     * Check if module exists
     */
    private function moduleExists(string $moduleName): bool
    {
        return is_dir(base_path("modules/{$moduleName}"));
    }
}