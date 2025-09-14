<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Health\Checks;

use TaiCrm\LaravelModularDdd\Health\Contracts\HealthCheckInterface;
use TaiCrm\LaravelModularDdd\Health\ValueObjects\HealthStatus;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;

class DependencyCheck implements HealthCheckInterface
{
    public function __construct(
        private ModuleManagerInterface $moduleManager
    ) {}

    public function check(ModuleInfo $module): array
    {
        $missingDependencies = [];
        $availableDependencies = [];
        $disabledDependencies = [];

        foreach ($module->dependencies as $dependency) {
            if (!$this->moduleManager->isInstalled($dependency)) {
                $missingDependencies[] = $dependency;
            } elseif (!$this->moduleManager->isEnabled($dependency)) {
                $disabledDependencies[] = $dependency;
            } else {
                $availableDependencies[] = $dependency;
            }
        }

        $details = [
            'total_dependencies' => count($module->dependencies),
            'available' => $availableDependencies,
            'disabled' => $disabledDependencies,
            'missing' => $missingDependencies,
        ];

        if (!empty($missingDependencies)) {
            return [
                'name' => $this->getName(),
                'status' => HealthStatus::Critical,
                'message' => 'Missing dependencies: ' . implode(', ', $missingDependencies),
                'details' => $details,
            ];
        }

        if (!empty($disabledDependencies)) {
            return [
                'name' => $this->getName(),
                'status' => HealthStatus::Warning,
                'message' => 'Disabled dependencies: ' . implode(', ', $disabledDependencies),
                'details' => $details,
            ];
        }

        return [
            'name' => $this->getName(),
            'status' => HealthStatus::Healthy,
            'message' => count($module->dependencies) > 0
                ? 'All dependencies are available and enabled'
                : 'No dependencies required',
            'details' => $details,
        ];
    }

    public function getName(): string
    {
        return 'Dependencies';
    }

    public function getDescription(): string
    {
        return 'Checks if all module dependencies are installed and enabled';
    }
}