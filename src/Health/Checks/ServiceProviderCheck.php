<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Health\Checks;

use TaiCrm\LaravelModularDdd\Health\Contracts\HealthCheckInterface;
use TaiCrm\LaravelModularDdd\Health\ValueObjects\HealthStatus;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;

class ServiceProviderCheck implements HealthCheckInterface
{
    public function check(ModuleInfo $module): array
    {
        $providerPath = $module->path . "/Providers/{$module->name}ServiceProvider.php";
        $details = [
            'expected_path' => $providerPath,
            'provider_class' => "Modules\\{$module->name}\\Providers\\{$module->name}ServiceProvider",
        ];

        if (!file_exists($providerPath)) {
            return [
                'name' => $this->getName(),
                'status' => HealthStatus::Warning,
                'message' => 'Service provider file not found (optional but recommended)',
                'details' => $details,
            ];
        }

        try {
            $content = file_get_contents($providerPath);
            $analysis = $this->analyzeServiceProvider($content, $module->name);

            return [
                'name' => $this->getName(),
                'status' => $analysis['status'],
                'message' => $analysis['message'],
                'details' => array_merge($details, $analysis['details']),
            ];

        } catch (\Exception $e) {
            return [
                'name' => $this->getName(),
                'status' => HealthStatus::Critical,
                'message' => 'Error analyzing service provider: ' . $e->getMessage(),
                'details' => array_merge($details, ['error' => $e->getMessage()]),
            ];
        }
    }

    private function analyzeServiceProvider(string $content, string $moduleName): array
    {
        $issues = [];
        $warnings = [];
        $info = [];

        // Check for correct namespace
        if (!preg_match("/namespace\s+Modules\\\\{$moduleName}\\\\Providers;/", $content)) {
            $issues[] = 'Incorrect namespace - should be Modules\\' . $moduleName . '\\Providers';
        }

        // Check for correct class name
        if (!preg_match("/class\s+{$moduleName}ServiceProvider/", $content)) {
            $issues[] = "Incorrect class name - should be {$moduleName}ServiceProvider";
        }

        // Check if extends ModuleServiceProvider
        if (preg_match('/extends\s+ModuleServiceProvider/', $content)) {
            $info[] = 'Extends ModuleServiceProvider (recommended)';
        } elseif (preg_match('/extends\s+ServiceProvider/', $content)) {
            $warnings[] = 'Extends Laravel ServiceProvider instead of ModuleServiceProvider';
        } else {
            $issues[] = 'Does not extend ServiceProvider or ModuleServiceProvider';
        }

        // Check for register method
        if (preg_match('/public\s+function\s+register\(\)/', $content)) {
            $info[] = 'Has register() method';
        }

        // Check for boot method
        if (preg_match('/public\s+function\s+boot\(\)/', $content)) {
            $info[] = 'Has boot() method';
        }

        // Check for module name property (with or without type declaration)
        if (preg_match('/protected\s+(?:string\s+)?\$moduleName\s*=/', $content)) {
            $info[] = 'Has moduleName property';
        } else {
            $warnings[] = 'Missing $moduleName property (recommended for ModuleServiceProvider)';
        }

        // Check for contracts array (with or without type declaration)
        if (preg_match('/protected\s+(?:array\s+)?\$contracts\s*=/', $content)) {
            $info[] = 'Has contracts registration';
        }

        // Check for services array (with or without type declaration)
        if (preg_match('/protected\s+(?:array\s+)?\$services\s*=/', $content)) {
            $info[] = 'Has services registration';
        }

        // Check for event listeners array (with or without type declaration)
        if (preg_match('/protected\s+(?:array\s+)?\$eventListeners\s*=/', $content)) {
            $info[] = 'Has event listeners registration';
        }

        // Determine overall status
        if (!empty($issues)) {
            return [
                'status' => HealthStatus::Critical,
                'message' => 'Service provider has critical issues: ' . implode(', ', $issues),
                'details' => [
                    'critical_issues' => $issues,
                    'warnings' => $warnings,
                    'info' => $info,
                ],
            ];
        }

        if (!empty($warnings)) {
            return [
                'status' => HealthStatus::Warning,
                'message' => 'Service provider has warnings: ' . implode(', ', $warnings),
                'details' => [
                    'warnings' => $warnings,
                    'info' => $info,
                    'suggestions' => $this->getProviderSuggestions($content),
                ],
            ];
        }

        return [
            'status' => HealthStatus::Healthy,
            'message' => 'Service provider is properly configured',
            'details' => [
                'info' => $info,
                'features' => $this->detectFeatures($content),
            ],
        ];
    }

    private function getProviderSuggestions(string $content): array
    {
        $suggestions = [];

        if (!preg_match('/loadMigrationsFrom/', $content)) {
            $suggestions[] = 'Consider adding migration loading in boot() method';
        }

        if (!preg_match('/loadRoutesFrom|Route::/', $content)) {
            $suggestions[] = 'Consider loading module routes in service provider';
        }

        if (!preg_match('/publishes/', $content)) {
            $suggestions[] = 'Consider adding publishable assets or config files';
        }

        return $suggestions;
    }

    private function detectFeatures(string $content): array
    {
        $features = [];

        if (preg_match('/loadMigrationsFrom/', $content)) {
            $features[] = 'Loads migrations';
        }

        if (preg_match('/loadRoutesFrom/', $content)) {
            $features[] = 'Loads routes';
        }

        if (preg_match('/publishes/', $content)) {
            $features[] = 'Publishes assets';
        }

        if (preg_match('/commands\(/', $content)) {
            $features[] = 'Registers console commands';
        }

        if (preg_match('/view\(\)|loadViewsFrom/', $content)) {
            $features[] = 'Registers views';
        }

        return $features;
    }

    public function getName(): string
    {
        return 'Service Provider';
    }

    public function getDescription(): string
    {
        return 'Checks if the module service provider is properly configured';
    }
}