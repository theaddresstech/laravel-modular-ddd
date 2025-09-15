<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use TaiCrm\LaravelModularDdd\Documentation\SwaggerDocumentationGenerator;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use Illuminate\Support\Facades\File;

class ModuleSwaggerGenerateCommand extends Command
{
    protected $signature = 'module:swagger:generate
                            {module? : Specific module to generate documentation for}
                            {--output= : Output directory for generated files}
                            {--format=json : Output format (json, yaml)}
                            {--individual : Generate individual module files}
                            {--combined : Generate combined documentation file}
                            {--ui : Generate Swagger UI HTML files}
                            {--serve : Start local server to serve documentation}
                            {--port=8080 : Port for local server}';

    protected $description = 'Generate comprehensive Swagger documentation files';

    public function __construct(
        private SwaggerDocumentationGenerator $generator,
        private ModuleManagerInterface $moduleManager
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $moduleName = $this->argument('module');
        $outputDir = $this->option('output') ?: config('modular-ddd.api.documentation.swagger.export.output_dir');
        $format = $this->option('format');
        $individual = $this->option('individual');
        $combined = $this->option('combined');
        $withUI = $this->option('ui');
        $serve = $this->option('serve');
        $port = $this->option('port');

        // Default to both individual and combined if none specified
        if (!$individual && !$combined) {
            $individual = true;
            $combined = true;
        }

        $this->info('📚 Generating Swagger documentation...');

        // Ensure output directory exists
        $this->ensureOutputDirectory($outputDir);

        $generatedFiles = [];

        if ($moduleName) {
            // Generate for specific module
            if (!$this->moduleExists($moduleName)) {
                $this->error("Module '{$moduleName}' does not exist.");
                return 1;
            }

            $files = $this->generateModuleDocumentation($moduleName, $outputDir, $format, $individual, $withUI);
            $generatedFiles = array_merge($generatedFiles, $files);
        } else {
            // Generate for all modules
            $files = $this->generateAllModulesDocumentation($outputDir, $format, $individual, $combined, $withUI);
            $generatedFiles = array_merge($generatedFiles, $files);
        }

        if (empty($generatedFiles)) {
            $this->warn('No documentation files were generated.');
            return 0;
        }

        $this->displayGeneratedFiles($generatedFiles);

        if ($serve) {
            $this->serveDocumentation($outputDir, $port);
        }

        $this->info('✅ Documentation generation completed!');
        return 0;
    }

    /**
     * Generate documentation for a specific module
     */
    private function generateModuleDocumentation(string $moduleName, string $outputDir, string $format, bool $individual, bool $withUI): array
    {
        $files = [];

        $this->line("📋 Generating documentation for module: {$moduleName}");

        $documentation = $this->generator->generateModuleDocumentation($moduleName);

        if (empty($documentation)) {
            $this->warn("No documentation found for module '{$moduleName}'");
            return $files;
        }

        if ($individual) {
            $file = $this->saveDocumentation($moduleName, $documentation, $outputDir, $format);
            $files[] = $file;
        }

        if ($withUI) {
            $uiFile = $this->generator->generateSwaggerUI($moduleName, $outputDir);
            $files[] = $uiFile;
        }

        return $files;
    }

    /**
     * Generate documentation for all modules
     */
    private function generateAllModulesDocumentation(string $outputDir, string $format, bool $individual, bool $combined, bool $withUI): array
    {
        $files = [];
        $modules = $this->moduleManager->list();
        $allDocumentation = [];

        $progressBar = $this->output->createProgressBar(count($modules));
        $progressBar->setFormat('%current%/%max% [%bar%] %percent:3s%% - %message%');
        $progressBar->setMessage('Starting generation...');
        $progressBar->start();

        foreach ($modules as $module) {
            if (!$module->isEnabled()) {
                $progressBar->setMessage("Skipping disabled: {$module->getName()}");
                $progressBar->advance();
                continue;
            }

            $progressBar->setMessage("Processing: {$module->getName()}");

            $documentation = $this->generator->generateModuleDocumentation($module->getName());

            if (!empty($documentation)) {
                $allDocumentation[$module->getName()] = $documentation;

                if ($individual) {
                    $file = $this->saveDocumentation($module->getName(), $documentation, $outputDir, $format);
                    $files[] = $file;
                }

                if ($withUI) {
                    $uiFile = $this->generator->generateSwaggerUI($module->getName(), $outputDir);
                    $files[] = $uiFile;
                }
            }

            $progressBar->advance();
        }

        $progressBar->setMessage('Processing completed');
        $progressBar->finish();
        $this->newLine();

        // Generate combined documentation
        if ($combined && !empty($allDocumentation)) {
            $this->line('📖 Generating combined documentation...');
            $combinedDoc = $this->generator->generateCombinedDocumentation($allDocumentation);
            $combinedFile = $this->saveDocumentation('api-documentation', $combinedDoc, $outputDir, $format);
            $files[] = $combinedFile;

            if ($withUI) {
                $combinedUIFile = $this->generateCombinedSwaggerUI($outputDir);
                $files[] = $combinedUIFile;
            }
        }

        return $files;
    }

    /**
     * Save documentation to file
     */
    private function saveDocumentation(string $name, array $documentation, string $outputDir, string $format): string
    {
        $extension = $format === 'yaml' ? 'yaml' : 'json';
        $filename = "{$outputDir}/{$name}.{$extension}";

        if ($format === 'yaml') {
            $content = $this->convertToYaml($documentation);
        } else {
            $jsonOptions = config('modular-ddd.api.documentation.swagger.export.json_options', JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $content = json_encode($documentation, $jsonOptions);
        }

        File::put($filename, $content);

        return $filename;
    }

    /**
     * Generate combined Swagger UI
     */
    private function generateCombinedSwaggerUI(string $outputDir): string
    {
        $appName = config('app.name', 'Laravel');
        $title = config('modular-ddd.api.documentation.swagger.title', 'Laravel Modular DDD API');
        $jsonUrl = '/api-documentation.json';

        $htmlContent = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$title} - Complete API Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui.css" />
    <style>
        html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin:0; background: #fafafa; }
        .swagger-ui .info .title { color: #3b4151; font-size: 36px; margin: 0; }
        .swagger-ui .info .description { margin: 15px 0; }
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
                defaultModelExpandDepth: 2,
                onComplete: function() {
                    console.log('Combined API documentation loaded');
                }
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
     * Convert array to YAML format
     */
    private function convertToYaml(array $data): string
    {
        // For simplicity, using JSON as YAML (valid YAML subset)
        // In production, you might want to use symfony/yaml or similar
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Display generated files
     */
    private function displayGeneratedFiles(array $files): void
    {
        $this->newLine();
        $this->info('📁 Generated Files:');

        $jsonFiles = array_filter($files, fn($file) => str_ends_with($file, '.json'));
        $yamlFiles = array_filter($files, fn($file) => str_ends_with($file, '.yaml'));
        $htmlFiles = array_filter($files, fn($file) => str_ends_with($file, '.html'));

        if (!empty($jsonFiles)) {
            $this->line('📄 JSON Documentation:');
            foreach ($jsonFiles as $file) {
                $this->line("  ├─ " . basename($file) . " (" . $this->formatFileSize($file) . ")");
            }
        }

        if (!empty($yamlFiles)) {
            $this->line('📄 YAML Documentation:');
            foreach ($yamlFiles as $file) {
                $this->line("  ├─ " . basename($file) . " (" . $this->formatFileSize($file) . ")");
            }
        }

        if (!empty($htmlFiles)) {
            $this->line('🌐 Swagger UI Files:');
            foreach ($htmlFiles as $file) {
                $this->line("  ├─ " . basename($file));
            }
        }

        $this->newLine();
        $this->line("📍 Output directory: " . dirname($files[0]));
    }

    /**
     * Format file size
     */
    private function formatFileSize(string $filepath): string
    {
        $bytes = filesize($filepath);
        $units = ['B', 'KB', 'MB', 'GB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Serve documentation with built-in PHP server
     */
    private function serveDocumentation(string $outputDir, string $port): void
    {
        $this->info("🚀 Starting documentation server on http://localhost:{$port}");
        $this->line("📍 Serving from: {$outputDir}");
        $this->line("🛑 Press Ctrl+C to stop the server");
        $this->newLine();

        // Check if there's an index file to serve
        $indexFiles = ['api-documentation-ui.html', 'index.html'];
        $indexFile = null;

        foreach ($indexFiles as $file) {
            if (File::exists("{$outputDir}/{$file}")) {
                $indexFile = $file;
                break;
            }
        }

        if ($indexFile) {
            $this->line("📖 Main documentation: http://localhost:{$port}/{$indexFile}");
        }

        // Start PHP built-in server
        $command = sprintf(
            'php -S localhost:%s -t %s',
            escapeshellarg($port),
            escapeshellarg($outputDir)
        );

        passthru($command);
    }

    /**
     * Ensure output directory exists
     */
    private function ensureOutputDirectory(string $outputDir): void
    {
        if (!File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
            $this->line("📁 Created output directory: {$outputDir}");
        }
    }

    /**
     * Check if module exists
     */
    private function moduleExists(string $moduleName): bool
    {
        return is_dir(base_path("modules/{$moduleName}"));
    }
}