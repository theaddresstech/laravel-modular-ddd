<?php

declare(strict_types=1);

/**
 * Laravel Modular DDD Package Validation Script
 *
 * This script validates the package structure, configuration, and functionality
 * before release to ensure everything is working correctly.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

class PackageValidationCommand extends Command
{
    private SymfonyStyle $io;
    private array $errors = [];
    private array $warnings = [];
    private array $results = [];

    protected function configure(): void
    {
        $this
            ->setName('validate')
            ->setDescription('Validate Laravel Modular DDD package');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->io->title('Laravel Modular DDD Package Validation');

        // Run all validation checks
        $this->validatePackageStructure();
        $this->validateComposerConfiguration();
        $this->validateServiceProvider();
        $this->validateCommands();
        $this->validateFoundationClasses();
        $this->validateExampleModules();
        $this->validateDocumentation();
        $this->validateTestSuite();

        // Display results
        $this->displayResults();

        return empty($this->errors) ? Command::SUCCESS : Command::FAILURE;
    }

    private function validatePackageStructure(): void
    {
        $this->io->section('Package Structure Validation');

        $requiredDirectories = [
            'src',
            'src/Commands',
            'src/Foundation',
            'src/ModuleManager',
            'src/Monitoring',
            'src/Security',
            'src/Visualization',
            'examples',
            'examples/ProductCatalog',
            'docs',
            'scripts',
            'tests',
            '.github',
            '.github/workflows',
            'docker',
        ];

        $requiredFiles = [
            'composer.json',
            'README.md',
            'CHANGELOG.md',
            'CONTRIBUTING.md',
            'LICENSE.md',
            'src/ModularDddServiceProvider.php',
            'examples/ProductCatalog/manifest.json',
            '.github/workflows/ci.yml',
            'docker/Dockerfile',
            'docker/docker-compose.yml',
            'scripts/release.sh',
        ];

        foreach ($requiredDirectories as $dir) {
            if (!is_dir($dir)) {
                $this->errors[] = "Missing required directory: {$dir}";
            } else {
                $this->results['directories'][] = $dir;
            }
        }

        foreach ($requiredFiles as $file) {
            if (!file_exists($file)) {
                $this->errors[] = "Missing required file: {$file}";
            } else {
                $this->results['files'][] = $file;
            }
        }

        $this->io->success(sprintf('Validated %d directories and %d files',
            count($this->results['directories'] ?? []),
            count($this->results['files'] ?? [])
        ));
    }

    private function validateComposerConfiguration(): void
    {
        $this->io->section('Composer Configuration Validation');

        if (!file_exists('composer.json')) {
            $this->errors[] = 'composer.json not found';
            return;
        }

        $composer = json_decode(file_get_contents('composer.json'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errors[] = 'Invalid JSON in composer.json';
            return;
        }

        // Required fields
        $requiredFields = [
            'name' => 'tai-crm/laravel-modular-ddd',
            'type' => 'library',
            'license' => 'MIT',
        ];

        foreach ($requiredFields as $field => $expectedValue) {
            if (!isset($composer[$field])) {
                $this->errors[] = "Missing required field in composer.json: {$field}";
            } elseif ($composer[$field] !== $expectedValue) {
                $this->warnings[] = "Field '{$field}' should be '{$expectedValue}', got '{$composer[$field]}'";
            }
        }

        // Check PHP version constraint
        if (!isset($composer['require']['php'])) {
            $this->errors[] = 'PHP version constraint not specified';
        } elseif (!str_contains($composer['require']['php'], '8.2')) {
            $this->warnings[] = 'PHP version should require 8.2 or higher';
        }

        // Check Laravel version constraint
        if (!isset($composer['require']['illuminate/support'])) {
            $this->errors[] = 'Laravel/Illuminate dependency not specified';
        }

        // Check autoloading
        if (!isset($composer['autoload']['psr-4']['TaiCrm\\LaravelModularDdd\\'])) {
            $this->errors[] = 'PSR-4 autoloading not configured correctly';
        }

        // Check service provider registration
        if (!isset($composer['extra']['laravel']['providers'])) {
            $this->warnings[] = 'Service provider not registered in composer.json extra section';
        }

        $this->io->success('Composer configuration validated');
    }

    private function validateServiceProvider(): void
    {
        $this->io->section('Service Provider Validation');

        $providerPath = 'src/ModularDddServiceProvider.php';

        if (!file_exists($providerPath)) {
            $this->errors[] = 'ModularDddServiceProvider.php not found';
            return;
        }

        $content = file_get_contents($providerPath);

        // Check for required methods
        $requiredMethods = [
            'register',
            'boot',
            'provides',
        ];

        foreach ($requiredMethods as $method) {
            if (!str_contains($content, "function {$method}(")) {
                $this->errors[] = "Missing required method in service provider: {$method}";
            }
        }

        // Check for service registrations
        $requiredServices = [
            'ModuleManager',
            'EventBus',
            'ServiceRegistry',
            'ModulePerformanceMonitor',
            'ModuleSecurityScanner',
            'DependencyGraphGenerator',
        ];

        foreach ($requiredServices as $service) {
            if (!str_contains($content, $service)) {
                $this->warnings[] = "Service '{$service}' may not be registered";
            }
        }

        $this->io->success('Service provider validated');
    }

    private function validateCommands(): void
    {
        $this->io->section('Commands Validation');

        $commandsDir = 'src/Commands';
        $expectedCommands = [
            'ModuleListCommand',
            'ModuleInstallCommand',
            'ModuleEnableCommand',
            'ModuleDisableCommand',
            'ModuleRemoveCommand',
            'ModuleMakeCommand',
            'ModuleStatusCommand',
            'ModuleHealthCommand',
            'ModuleMigrateCommand',
            'ModuleSeedCommand',
            'ModuleCacheCommand',
            'ModuleUpdateCommand',
            'ModuleBackupCommand',
            'ModuleRestoreCommand',
            'ModuleDevCommand',
            'ModuleStubCommand',
            'ModuleMetricsCommand',
            'ModuleVisualizationCommand',
            'ModuleSecurityCommand',
        ];

        $foundCommands = [];

        if (is_dir($commandsDir)) {
            $files = glob($commandsDir . '/*.php');
            foreach ($files as $file) {
                $className = basename($file, '.php');
                $foundCommands[] = $className;
            }
        }

        foreach ($expectedCommands as $command) {
            if (!in_array($command, $foundCommands)) {
                $this->errors[] = "Missing command class: {$command}";
            }
        }

        $this->io->success(sprintf('Validated %d command classes', count($foundCommands)));
    }

    private function validateFoundationClasses(): void
    {
        $this->io->section('Foundation Classes Validation');

        $foundationDir = 'src/Foundation';
        $expectedClasses = [
            'AggregateRoot',
            'Entity',
            'ValueObject',
            'DomainEvent',
            'EventBus',
            'ServiceRegistry',
        ];

        foreach ($expectedClasses as $class) {
            $filePath = "{$foundationDir}/{$class}.php";
            if (!file_exists($filePath)) {
                $this->errors[] = "Missing foundation class: {$class}";
            } else {
                // Basic content validation
                $content = file_get_contents($filePath);
                if (!str_contains($content, "class {$class}")) {
                    $this->errors[] = "Foundation class {$class} may be malformed";
                }
            }
        }

        $this->io->success('Foundation classes validated');
    }

    private function validateExampleModules(): void
    {
        $this->io->section('Example Modules Validation');

        $productCatalogPath = 'examples/ProductCatalog';

        if (!is_dir($productCatalogPath)) {
            $this->errors[] = 'ProductCatalog example module not found';
            return;
        }

        // Check manifest file
        $manifestPath = $productCatalogPath . '/manifest.json';
        if (!file_exists($manifestPath)) {
            $this->errors[] = 'ProductCatalog manifest.json not found';
        } else {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->errors[] = 'Invalid JSON in ProductCatalog manifest.json';
            }
        }

        // Check required directories
        $requiredDirs = [
            'Domain',
            'Application',
            'Infrastructure',
            'Presentation',
            'Database',
            'Tests',
            'Config',
        ];

        foreach ($requiredDirs as $dir) {
            $dirPath = $productCatalogPath . '/' . $dir;
            if (!is_dir($dirPath)) {
                $this->warnings[] = "ProductCatalog missing directory: {$dir}";
            }
        }

        $this->io->success('Example modules validated');
    }

    private function validateDocumentation(): void
    {
        $this->io->section('Documentation Validation');

        $docFiles = [
            'README.md',
            'CHANGELOG.md',
            'CONTRIBUTING.md',
        ];

        foreach ($docFiles as $file) {
            if (!file_exists($file)) {
                $this->errors[] = "Missing documentation file: {$file}";
                continue;
            }

            $content = file_get_contents($file);

            // Basic content validation
            if (strlen($content) < 100) {
                $this->warnings[] = "Documentation file {$file} seems too short";
            }

            // Check for basic structure
            if ($file === 'README.md' && !str_contains($content, '# Laravel Modular DDD')) {
                $this->warnings[] = 'README.md missing main title';
            }
        }

        $this->io->success('Documentation validated');
    }

    private function validateTestSuite(): void
    {
        $this->io->section('Test Suite Validation');

        if (!is_dir('tests')) {
            $this->warnings[] = 'No tests directory found';
            return;
        }

        // Check for PHPUnit configuration
        $phpunitFiles = ['phpunit.xml', 'phpunit.xml.dist'];
        $hasPhpunitConfig = false;

        foreach ($phpunitFiles as $file) {
            if (file_exists($file)) {
                $hasPhpunitConfig = true;
                break;
            }
        }

        if (!$hasPhpunitConfig) {
            $this->warnings[] = 'PHPUnit configuration file not found';
        }

        // Count test files
        $testFiles = glob('tests/**/*Test.php');
        if (empty($testFiles)) {
            $this->warnings[] = 'No test files found';
        }

        $this->io->success(sprintf('Found %d test files', count($testFiles)));
    }

    private function displayResults(): void
    {
        $this->io->section('Validation Summary');

        // Create summary table
        $table = new Table($this->io);
        $table->setHeaders(['Category', 'Status', 'Details']);

        $totalChecks = count($this->results['directories'] ?? []) +
                      count($this->results['files'] ?? []) +
                      count($this->errors) +
                      count($this->warnings);

        $table->addRow(['Total Checks', $totalChecks, 'Files, directories, and validations']);
        $table->addRow(['Errors', count($this->errors), count($this->errors) > 0 ? 'Critical issues found' : 'None']);
        $table->addRow(['Warnings', count($this->warnings), count($this->warnings) > 0 ? 'Minor issues found' : 'None']);

        $table->render();

        // Display errors
        if (!empty($this->errors)) {
            $this->io->error('Validation Errors:');
            foreach ($this->errors as $error) {
                $this->io->writeln("  • {$error}");
            }
        }

        // Display warnings
        if (!empty($this->warnings)) {
            $this->io->warning('Validation Warnings:');
            foreach ($this->warnings as $warning) {
                $this->io->writeln("  • {$warning}");
            }
        }

        // Final status
        if (empty($this->errors)) {
            $this->io->success('Package validation completed successfully!');
            if (!empty($this->warnings)) {
                $this->io->note('Some warnings were found, but they are not critical.');
            }
        } else {
            $this->io->error('Package validation failed. Please fix the errors above.');
        }
    }
}

// Create and run the application
$application = new Application('Laravel Modular DDD Package Validator', '1.0.0');
$application->add(new PackageValidationCommand());
$application->setDefaultCommand('validate');
$application->run();