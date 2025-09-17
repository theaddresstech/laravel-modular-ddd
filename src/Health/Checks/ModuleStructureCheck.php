<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Health\Checks;

use TaiCrm\LaravelModularDdd\Health\Contracts\HealthCheckInterface;
use TaiCrm\LaravelModularDdd\Health\ValueObjects\HealthStatus;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;

class ModuleStructureCheck implements HealthCheckInterface
{
    public function check(ModuleInfo $module): array
    {
        $requiredDirectories = [
            'Domain',
            'Application',
            'Infrastructure',
            'Presentation',
        ];

        $requiredFiles = [
            'manifest.json',
        ];

        $missing = [];
        $details = [];

        // Check directories
        foreach ($requiredDirectories as $directory) {
            $path = $module->path . '/' . $directory;
            if (!is_dir($path)) {
                $missing[] = $directory;
            } else {
                $details['directories'][] = $directory . ' ✓';
            }
        }

        // Check files
        foreach ($requiredFiles as $file) {
            $path = $module->path . '/' . $file;
            if (!is_file($path)) {
                $missing[] = $file;
            } else {
                $details['files'][] = $file . ' ✓';
            }
        }

        if (empty($missing)) {
            return [
                'name' => $this->getName(),
                'status' => HealthStatus::Healthy,
                'message' => 'Module structure is valid',
                'details' => $details,
            ];
        }

        return [
            'name' => $this->getName(),
            'status' => HealthStatus::Critical,
            'message' => 'Missing required structure: ' . implode(', ', $missing),
            'details' => [
                'missing' => $missing,
                'present' => $details,
            ],
        ];
    }

    public function getName(): string
    {
        return 'Module Structure';
    }

    public function getDescription(): string
    {
        return 'Validates that the module has the required DDD directory structure';
    }
}
