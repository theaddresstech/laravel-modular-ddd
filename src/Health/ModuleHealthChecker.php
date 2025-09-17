<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Health;

use Exception;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\Health\Contracts\HealthCheckInterface;
use TaiCrm\LaravelModularDdd\Health\ValueObjects\HealthReport;
use TaiCrm\LaravelModularDdd\Health\ValueObjects\HealthStatus;

class ModuleHealthChecker
{
    private Collection $healthChecks;

    public function __construct(
        private ModuleManagerInterface $moduleManager,
        private LoggerInterface $logger,
    ) {
        $this->healthChecks = collect();
        $this->registerDefaultChecks();
    }

    public function checkModule(string $moduleName): HealthReport
    {
        $module = $this->moduleManager->getInfo($moduleName);

        if (!$module) {
            return HealthReport::failed($moduleName, 'Module not found');
        }

        $checks = collect();
        $overallStatus = HealthStatus::Healthy;

        foreach ($this->healthChecks as $check) {
            try {
                $result = $check->check($module);
                $checks->push($result);

                if ($result['status'] === HealthStatus::Critical) {
                    $overallStatus = HealthStatus::Critical;
                } elseif ($result['status'] === HealthStatus::Warning && $overallStatus === HealthStatus::Healthy) {
                    $overallStatus = HealthStatus::Warning;
                }
            } catch (Exception $e) {
                $this->logger->error("Health check failed for module {$moduleName}: " . $e->getMessage());

                $checks->push([
                    'name' => $check::class,
                    'status' => HealthStatus::Critical,
                    'message' => 'Health check failed: ' . $e->getMessage(),
                    'details' => [],
                ]);

                $overallStatus = HealthStatus::Critical;
            }
        }

        return new HealthReport(
            moduleName: $moduleName,
            status: $overallStatus,
            checks: $checks->toArray(),
            timestamp: now(),
        );
    }

    public function checkAllModules(): Collection
    {
        $modules = $this->moduleManager->list();
        $reports = collect();

        foreach ($modules as $module) {
            if ($module->isEnabled()) {
                $reports->put($module->name, $this->checkModule($module->name));
            }
        }

        return $reports;
    }

    public function addHealthCheck(HealthCheckInterface $check): void
    {
        $this->healthChecks->push($check);
    }

    public function removeHealthCheck(string $checkClass): void
    {
        $this->healthChecks = $this->healthChecks->reject(
            static fn ($check) => $check::class === $checkClass,
        );
    }

    public function getHealthChecks(): Collection
    {
        return $this->healthChecks;
    }

    private function registerDefaultChecks(): void
    {
        $this->addHealthCheck(new Checks\ModuleStructureCheck());
        $this->addHealthCheck(new Checks\DependencyCheck($this->moduleManager));
        $this->addHealthCheck(new Checks\ManifestValidationCheck());
        $this->addHealthCheck(new Checks\ServiceProviderCheck());
    }
}
