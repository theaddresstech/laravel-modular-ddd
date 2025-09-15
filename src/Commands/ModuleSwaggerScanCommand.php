<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use TaiCrm\LaravelModularDdd\Documentation\SwaggerAnnotationScanner;
use TaiCrm\LaravelModularDdd\Documentation\SwaggerDocumentationGenerator;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use Illuminate\Support\Facades\File;

class ModuleSwaggerScanCommand extends Command
{
    protected $signature = 'module:swagger:scan
                            {module? : Specific module to scan (optional)}
                            {--export : Export documentation to files}
                            {--format=json : Export format (json, yaml)}
                            {--output= : Output directory for exported files}
                            {--combined : Generate combined documentation}
                            {--ui : Generate Swagger UI HTML files}
                            {--cache : Use cached results if available}
                            {--fresh : Force fresh scan, ignore cache}';

    protected $description = 'Scan modules for Swagger annotations and generate documentation';

    public function __construct(
        private SwaggerAnnotationScanner $scanner,
        private SwaggerDocumentationGenerator $generator,
        private ModuleManagerInterface $moduleManager
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $moduleName = $this->argument('module');
        $withExport = $this->option('export');
        $format = $this->option('format');
        $outputDir = $this->option('output') ?: config('modular-ddd.api.documentation.swagger.export.output_dir');
        $withCombined = $this->option('combined');
        $withUI = $this->option('ui');
        $useCache = $this->option('cache') && !$this->option('fresh');

        $this->info('🔍 Scanning modules for Swagger annotations...');

        if ($moduleName) {
            // Scan specific module
            $result = $this->scanSingleModule($moduleName, $useCache);
            if (empty($result)) {
                $this->error("No Swagger documentation found for module '{$moduleName}'");
                return 1;
            }
        } else {
            // Scan all modules
            $result = $this->scanAllModules($useCache);
            if (empty($result)) {
                $this->warn('No Swagger documentation found in any modules');
                return 0;
            }
        }

        $this->displayScanResults($result);

        if ($withExport) {
            $this->exportDocumentation($result, $outputDir, $format, $withCombined, $withUI);
        }

        $this->info('✅ Swagger scan completed successfully!');
        return 0;
    }

    /**
     * Scan a single module
     */
    private function scanSingleModule(string $moduleName, bool $useCache): array
    {
        if (!$this->moduleExists($moduleName)) {
            $this->error("Module '{$moduleName}' does not exist.");
            return [];
        }

        $this->line("📋 Scanning module: {$moduleName}");

        $result = $this->scanner->scanModule($moduleName);

        if (!empty($result['paths']) || !empty($result['components']['schemas'])) {
            return [$moduleName => $result];
        }

        return [];
    }

    /**
     * Scan all modules
     */
    private function scanAllModules(bool $useCache): array
    {
        $modules = $this->moduleManager->list();
        $results = [];

        $progressBar = $this->output->createProgressBar(count($modules));
        $progressBar->setFormat('%current%/%max% [%bar%] %percent:3s%% - %message%');
        $progressBar->setMessage('Starting scan...');
        $progressBar->start();

        foreach ($modules as $module) {
            if (!$module->isEnabled()) {
                $progressBar->setMessage("Skipping disabled: {$module->name}");
                $progressBar->advance();
                continue;
            }

            $progressBar->setMessage("Scanning: {$module->name}");

            $result = $this->scanner->scanModule($module->name);

            if (!empty($result['paths']) || !empty($result['components']['schemas'])) {
                $results[$module->name] = $result;
            }

            $progressBar->advance();
        }

        $progressBar->setMessage('Scan completed');
        $progressBar->finish();
        $this->newLine();

        return $results;
    }

    /**
     * Display scan results
     */
    private function displayScanResults(array $results): void
    {
        $this->newLine();
        $this->info('📊 Scan Results:');

        $totalPaths = 0;
        $totalSchemas = 0;

        $headers = ['Module', 'Paths', 'Schemas', 'Status'];
        $rows = [];

        foreach ($results as $moduleName => $data) {
            $pathCount = count($data['paths'] ?? []);
            $schemaCount = count($data['components']['schemas'] ?? []);

            $totalPaths += $pathCount;
            $totalSchemas += $schemaCount;

            $status = ($pathCount > 0 || $schemaCount > 0) ? '✅ Found' : '⚠️  Empty';

            $rows[] = [
                $moduleName,
                $pathCount,
                $schemaCount,
                $status,
            ];
        }

        // Add summary row
        $rows[] = ['---', '---', '---', '---'];
        $rows[] = ['TOTAL', $totalPaths, $totalSchemas, count($results) . ' modules'];

        $this->table($headers, $rows);

        if ($totalPaths > 0) {
            $this->info("🎯 Found {$totalPaths} API endpoints across " . count($results) . " modules");
        }

        if ($totalSchemas > 0) {
            $this->info("📝 Found {$totalSchemas} schema definitions");
        }
    }

    /**
     * Export documentation to files
     */
    private function exportDocumentation(array $results, string $outputDir, string $format, bool $withCombined, bool $withUI): void
    {
        $this->info('📤 Exporting documentation...');

        // Ensure output directory exists
        if (!File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
            $this->line("Created output directory: {$outputDir}");
        }

        $exportedFiles = [];

        // Export individual module files
        foreach ($results as $moduleName => $data) {
            $documentation = $this->generator->generateModuleDocumentation($moduleName);

            if (!empty($documentation)) {
                $filename = $this->exportModuleDocumentation($moduleName, $documentation, $outputDir, $format);
                $exportedFiles[] = $filename;

                if ($withUI) {
                    $uiFilename = $this->generator->generateSwaggerUI($moduleName, $outputDir);
                    $exportedFiles[] = $uiFilename;
                    $this->line("Generated UI: {$uiFilename}");
                }
            }
        }

        // Export combined documentation
        if ($withCombined && count($results) > 1) {
            $combinedDoc = $this->generator->generateCombinedDocumentation($results);
            $combinedFilename = $this->exportCombinedDocumentation($combinedDoc, $outputDir, $format);
            $exportedFiles[] = $combinedFilename;

            if ($withUI) {
                $combinedUIFilename = $this->generateCombinedSwaggerUI($outputDir);
                $exportedFiles[] = $combinedUIFilename;
                $this->line("Generated combined UI: {$combinedUIFilename}");
            }
        }

        $this->info('✅ Export completed!');
        $this->line('Generated files:');
        foreach ($exportedFiles as $file) {
            $this->line("  - {$file}");
        }
    }

    /**
     * Export module documentation
     */
    private function exportModuleDocumentation(string $moduleName, array $documentation, string $outputDir, string $format): string
    {
        $extension = $format === 'yaml' ? 'yaml' : 'json';
        $filename = "{$outputDir}/{$moduleName}.{$extension}";

        if ($format === 'yaml') {
            // Convert to YAML (basic implementation)
            $content = $this->arrayToYaml($documentation);
        } else {
            $jsonOptions = config('modular-ddd.api.documentation.swagger.export.json_options', JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $content = json_encode($documentation, $jsonOptions);
        }

        File::put($filename, $content);
        $this->line("Exported {$moduleName}: {$filename}");

        return $filename;
    }

    /**
     * Export combined documentation
     */
    private function exportCombinedDocumentation(array $documentation, string $outputDir, string $format): string
    {
        $extension = $format === 'yaml' ? 'yaml' : 'json';
        $filename = "{$outputDir}/api-documentation.{$extension}";

        if ($format === 'yaml') {
            $content = $this->arrayToYaml($documentation);
        } else {
            $jsonOptions = config('modular-ddd.api.documentation.swagger.export.json_options', JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $content = json_encode($documentation, $jsonOptions);
        }

        File::put($filename, $content);
        $this->line("Exported combined documentation: {$filename}");

        return $filename;
    }

    /**
     * Generate combined Swagger UI
     */
    private function generateCombinedSwaggerUI(string $outputDir): string
    {
        $appName = config('app.name', 'Laravel');
        $jsonUrl = '/api-documentation.json';

        $htmlContent = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$appName} - Complete API Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui.css" />
    <style>
        html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin:0; background: #fafafa; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: '{$jsonUrl}',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
                plugins: [SwaggerUIBundle.plugins.DownloadUrl],
                layout: "StandaloneLayout",
                tryItOutEnabled: true,
                supportedSubmitMethods: ['get', 'post', 'put', 'delete', 'patch'],
                persistAuthorization: true,
                displayRequestDuration: true,
                docExpansion: 'list',
                filter: true,
                showExtensions: true,
                showCommonExtensions: true,
                defaultModelsExpandDepth: 2,
                defaultModelExpandDepth: 2
            });
        };
    </script>
</body>
</html>
HTML;

        $filename = "{$outputDir}/api-documentation-ui.html";
        File::put($filename, $htmlContent);

        return $filename;
    }

    /**
     * Simple array to YAML conversion
     */
    private function arrayToYaml(array $array, int $indent = 0): string
    {
        $yaml = '';
        $spaces = str_repeat('  ', $indent);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $yaml .= $spaces . $key . ":\n";
                $yaml .= $this->arrayToYaml($value, $indent + 1);
            } else {
                $yaml .= $spaces . $key . ': ' . $this->yamlValue($value) . "\n";
            }
        }

        return $yaml;
    }

    /**
     * Format value for YAML
     */
    private function yamlValue($value): string
    {
        if (is_string($value)) {
            return '"' . addslashes($value) . '"';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        return (string) $value;
    }

    /**
     * Check if module exists
     */
    private function moduleExists(string $moduleName): bool
    {
        return is_dir(base_path("modules/{$moduleName}"));
    }
}