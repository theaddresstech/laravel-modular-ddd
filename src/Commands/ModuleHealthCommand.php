<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use TaiCrm\LaravelModularDdd\Health\ModuleHealthChecker;
use TaiCrm\LaravelModularDdd\Health\ValueObjects\HealthStatus;
use TaiCrm\LaravelModularDdd\Exceptions\ModuleNotFoundException;
use Illuminate\Console\Command;

class ModuleHealthCommand extends Command
{
    protected $signature = 'module:health {module? : Check specific module} {--all : Check all enabled modules} {--detailed : Show detailed output}';

    protected $description = 'Check module health status';

    public function __construct(
        private ModuleHealthChecker $healthChecker
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->checkAllModules();
        }

        $moduleName = $this->argument('module');
        if (!$moduleName) {
            $this->error('Please specify a module name or use --all flag');
            return self::FAILURE;
        }

        return $this->checkSingleModule($moduleName);
    }

    private function checkSingleModule(string $moduleName): int
    {
        try {
            $report = $this->healthChecker->checkModule($moduleName);

            $this->displayModuleHealth($report);

            return $report->isCritical() ? self::FAILURE : self::SUCCESS;

        } catch (ModuleNotFoundException $e) {
            $this->error("❌ " . $e->getMessage());
            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error("❌ Health check failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function checkAllModules(): int
    {
        $this->info('🔍 Checking health of all enabled modules...');
        $this->newLine();

        $reports = $this->healthChecker->checkAllModules();

        if ($reports->isEmpty()) {
            $this->info('No enabled modules found.');
            return self::SUCCESS;
        }

        $overallStatus = HealthStatus::Healthy;
        $summary = [
            'total' => $reports->count(),
            'healthy' => 0,
            'warnings' => 0,
            'critical' => 0,
        ];

        foreach ($reports as $report) {
            $this->displayModuleHealth($report);

            match ($report->status) {
                HealthStatus::Healthy => $summary['healthy']++,
                HealthStatus::Warning => $summary['warnings']++,
                HealthStatus::Critical => $summary['critical']++,
            };

            if ($report->isCritical()) {
                $overallStatus = HealthStatus::Critical;
            } elseif ($report->hasWarnings() && $overallStatus === HealthStatus::Healthy) {
                $overallStatus = HealthStatus::Warning;
            }
        }

        $this->displaySummary($summary, $overallStatus);

        return $overallStatus === HealthStatus::Critical ? self::FAILURE : self::SUCCESS;
    }

    private function displayModuleHealth($report): void
    {
        $icon = $report->status->getIcon();
        $color = $report->status->getColor();

        $this->line("{$icon} <fg={$color}>{$report->moduleName}</fg> ({$report->status->value})");

        if ($this->option('detailed') || !$report->isHealthy()) {
            $this->displayDetailedChecks($report);
        }

        $this->newLine();
    }

    private function displayDetailedChecks($report): void
    {
        foreach ($report->checks as $check) {
            if ($check['status'] instanceof HealthStatus) {
                $status = $check['status'];
            } elseif (is_string($check['status'])) {
                $status = HealthStatus::from($check['status']);
            } else {
                $status = HealthStatus::Critical;
            }

            $icon = $status->getIcon();
            $color = $status->getColor();

            $this->line("  {$icon} <fg={$color}>{$check['name']}</fg>: {$check['message']}");

            if ($this->option('detailed') && !empty($check['details'])) {
                foreach ($check['details'] as $key => $value) {
                    if (is_array($value)) {
                        $this->line("      {$key}: " . implode(', ', $value));
                    } else {
                        $this->line("      {$key}: {$value}");
                    }
                }
            }
        }
    }

    private function displaySummary(array $summary, HealthStatus $overallStatus): void
    {
        $this->newLine();
        $this->line('📊 <comment>Health Summary:</comment>');

        $icon = $overallStatus->getIcon();
        $color = $overallStatus->getColor();

        $this->line("Overall Status: {$icon} <fg={$color}>{$overallStatus->value}</fg>");
        $this->line("Total Modules: {$summary['total']}");
        $this->line("  <info>✅ Healthy:</info> {$summary['healthy']}");

        if ($summary['warnings'] > 0) {
            $this->line("  <comment>⚠️  Warnings:</comment> {$summary['warnings']}");
        }

        if ($summary['critical'] > 0) {
            $this->line("  <error>❌ Critical:</error> {$summary['critical']}");
        }

        if ($summary['critical'] > 0) {
            $this->newLine();
            $this->error('🚨 Some modules have critical health issues that need attention!');
        } elseif ($summary['warnings'] > 0) {
            $this->newLine();
            $this->warn('⚠️  Some modules have warnings that should be reviewed.');
        }
    }
}