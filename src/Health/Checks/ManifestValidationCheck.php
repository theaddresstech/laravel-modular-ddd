<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Health\Checks;

use Exception;
use TaiCrm\LaravelModularDdd\Health\Contracts\HealthCheckInterface;
use TaiCrm\LaravelModularDdd\Health\ValueObjects\HealthStatus;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;

class ManifestValidationCheck implements HealthCheckInterface
{
    private array $requiredFields = [
        'name',
        'display_name',
        'version',
        'author',
    ];
    private array $arrayFields = [
        'dependencies',
        'optional_dependencies',
        'conflicts',
        'provides',
    ];

    public function check(ModuleInfo $module): array
    {
        $manifestPath = $module->path . '/manifest.json';

        if (!file_exists($manifestPath)) {
            return [
                'name' => $this->getName(),
                'status' => HealthStatus::Critical,
                'message' => 'Manifest file is missing',
                'details' => ['path' => $manifestPath],
            ];
        }

        try {
            $content = file_get_contents($manifestPath);
            $manifest = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'name' => $this->getName(),
                    'status' => HealthStatus::Critical,
                    'message' => 'Invalid JSON in manifest: ' . json_last_error_msg(),
                    'details' => ['json_error' => json_last_error_msg()],
                ];
            }

            return $this->validateManifestStructure($manifest);
        } catch (Exception $e) {
            return [
                'name' => $this->getName(),
                'status' => HealthStatus::Critical,
                'message' => 'Error reading manifest: ' . $e->getMessage(),
                'details' => ['error' => $e->getMessage()],
            ];
        }
    }

    public function getName(): string
    {
        return 'Manifest Validation';
    }

    public function getDescription(): string
    {
        return 'Validates the module manifest.json file structure and content';
    }

    private function validateManifestStructure(array $manifest): array
    {
        $issues = [];
        $warnings = [];

        // Check required fields
        foreach ($this->requiredFields as $field) {
            if (!isset($manifest[$field]) || empty($manifest[$field])) {
                $issues[] = "Missing required field: {$field}";
            }
        }

        // Check array fields are actually arrays
        foreach ($this->arrayFields as $field) {
            if (isset($manifest[$field]) && !is_array($manifest[$field])) {
                $issues[] = "Field '{$field}' must be an array";
            }
        }

        // Validate version format
        if (isset($manifest['version'])) {
            if (!preg_match('/^\d+\.\d+\.\d+/', $manifest['version'])) {
                $warnings[] = 'Version format should follow semantic versioning (e.g., 1.0.0)';
            }
        }

        // Check for circular dependencies
        if (isset($manifest['dependencies']) && is_array($manifest['dependencies'])) {
            if (in_array($manifest['name'] ?? '', $manifest['dependencies'])) {
                $issues[] = 'Module cannot depend on itself';
            }
        }

        // Check for conflicting with self
        if (isset($manifest['conflicts']) && is_array($manifest['conflicts'])) {
            if (in_array($manifest['name'] ?? '', $manifest['conflicts'])) {
                $issues[] = 'Module cannot conflict with itself';
            }
        }

        if (!empty($issues)) {
            return [
                'name' => $this->getName(),
                'status' => HealthStatus::Critical,
                'message' => 'Manifest validation failed: ' . implode(', ', $issues),
                'details' => [
                    'critical_issues' => $issues,
                    'warnings' => $warnings,
                ],
            ];
        }

        if (!empty($warnings)) {
            return [
                'name' => $this->getName(),
                'status' => HealthStatus::Warning,
                'message' => 'Manifest has warnings: ' . implode(', ', $warnings),
                'details' => [
                    'warnings' => $warnings,
                    'suggestions' => $this->getSuggestions($manifest),
                ],
            ];
        }

        return [
            'name' => $this->getName(),
            'status' => HealthStatus::Healthy,
            'message' => 'Manifest is valid and well-formed',
            'details' => [
                'version' => $manifest['version'] ?? 'unknown',
                'dependencies_count' => count($manifest['dependencies'] ?? []),
                'provides_count' => count($manifest['provides'] ?? []),
            ],
        ];
    }

    private function getSuggestions(array $manifest): array
    {
        $suggestions = [];

        if (empty($manifest['description'] ?? '')) {
            $suggestions[] = 'Add a description to help users understand the module purpose';
        }

        if (empty($manifest['provides'] ?? [])) {
            $suggestions[] = 'Consider documenting what services/contracts this module provides';
        }

        if (!isset($manifest['config'])) {
            $suggestions[] = 'Add configuration options for module customization';
        }

        return $suggestions;
    }
}
