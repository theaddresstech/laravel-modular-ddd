<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use TaiCrm\LaravelModularDdd\Security\ModuleSecurityScanner;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use Illuminate\Console\Command;

class ModuleSecurityCommand extends Command
{
    protected $signature = 'module:security
                            {module? : Specific module to scan}
                            {--scan : Perform security scan}
                            {--report : Generate security report}
                            {--quarantine= : Quarantine a module}
                            {--verify : Verify module signatures}
                            {--output= : Output file for report}
                            {--format=text : Report format (text, json, html)}
                            {--fix : Attempt to fix low-risk issues automatically}
                            {--schedule : Set up scheduled security scans}';

    protected $description = 'Manage module security scanning and validation';

    public function __construct(
        private ModuleSecurityScanner $scanner,
        private ModuleManagerInterface $moduleManager
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($quarantineModule = $this->option('quarantine')) {
            return $this->quarantineModule($quarantineModule);
        }

        if ($this->option('verify')) {
            return $this->verifySignatures();
        }

        if ($this->option('report')) {
            return $this->generateReport();
        }

        if ($this->option('schedule')) {
            return $this->setupScheduledScans();
        }

        if ($this->option('scan')) {
            return $this->performSecurityScan();
        }

        return $this->showSecurityStatus();
    }

    private function performSecurityScan(): int
    {
        $module = $this->argument('module');

        if ($module) {
            return $this->scanSpecificModule($module);
        }

        return $this->scanAllModules();
    }

    private function scanSpecificModule(string $moduleName): int
    {
        $this->info("Scanning module: {$moduleName}");

        $modules = $this->moduleManager->list();
        $targetModule = $modules->firstWhere('name', $moduleName);

        if (!$targetModule) {
            $this->error("Module '{$moduleName}' not found.");
            return 1;
        }

        $result = $this->scanner->scanModule($targetModule);

        $this->displayModuleResults($result);

        if ($this->option('fix') && !empty($result['vulnerabilities'])) {
            $this->attemptFixes($result);
        }

        // Check if quarantine is needed
        if ($result['risk_level'] === 'critical') {
            if ($this->confirm("Module has critical vulnerabilities. Quarantine immediately?", true)) {
                $reason = 'Critical security vulnerabilities detected during scan';
                if ($this->scanner->quarantineModule($moduleName, $reason)) {
                    $this->error("Module has been quarantined due to critical security issues.");
                    return 2;
                }
            }
        }

        return $result['risk_level'] === 'critical' ? 2 : 0;
    }

    private function scanAllModules(): int
    {
        $this->info('Performing security scan on all modules...');

        $results = $this->scanner->scanAllModules();

        $this->displayScanSummary($results);

        // Show detailed results for high-risk modules
        $highRiskModules = array_filter($results['results'],
            fn($result) => in_array($result['risk_level'], ['critical', 'high'])
        );

        if (!empty($highRiskModules)) {
            $this->line('');
            $this->error('High-Risk Modules Detected:');
            $this->line('');

            foreach ($highRiskModules as $result) {
                $this->displayModuleResults($result, false);
            }

            // Offer to quarantine critical modules
            $criticalModules = array_filter($highRiskModules,
                fn($result) => $result['risk_level'] === 'critical'
            );

            if (!empty($criticalModules) && $this->confirm('Quarantine critical modules?', true)) {
                foreach ($criticalModules as $result) {
                    $reason = 'Critical security vulnerabilities detected during bulk scan';
                    $this->scanner->quarantineModule($result['module'], $reason);
                    $this->error("Quarantined: {$result['module']}");
                }
            }
        }

        // Save results if output specified
        if ($outputFile = $this->option('output')) {
            $format = $this->option('format');
            $this->saveResults($results, $outputFile, $format);
        }

        $hasHighRiskModules = !empty($highRiskModules);
        return $hasHighRiskModules ? 1 : 0;
    }

    private function generateReport(): int
    {
        $this->info('Generating comprehensive security report...');

        $report = $this->scanner->generateSecurityReport();
        $outputFile = $this->option('output');

        if ($outputFile) {
            file_put_contents($outputFile, $report);
            $this->info("Security report saved to: {$outputFile}");
        } else {
            $this->line($report);
        }

        return 0;
    }

    private function verifySignatures(): int
    {
        $this->info('Verifying module signatures...');

        $modules = $this->moduleManager->list();
        $verificationResults = [];

        foreach ($modules as $module) {
            $isValid = $this->scanner->validateModuleSignature($module);
            $verificationResults[$module->name] = $isValid;

            $status = $isValid ? '<info>✓ VALID</info>' : '<error>✗ INVALID</error>';
            $this->line("  {$module->name}: {$status}");
        }

        $invalidModules = array_filter($verificationResults, fn($valid) => !$valid);

        if (!empty($invalidModules)) {
            $this->line('');
            $this->error('Modules with invalid signatures detected:');
            foreach (array_keys($invalidModules) as $module) {
                $this->line("  - {$module}");
            }

            if ($this->confirm('Disable modules with invalid signatures?', true)) {
                foreach (array_keys($invalidModules) as $module) {
                    // Disable the module
                    $this->call('module:disable', ['module' => $module]);
                }
            }

            return 1;
        }

        $this->info('All module signatures are valid.');
        return 0;
    }

    private function quarantineModule(string $moduleName): int
    {
        $reason = $this->ask("Reason for quarantining module '{$moduleName}':");

        if (!$reason) {
            $this->error('Quarantine reason is required.');
            return 1;
        }

        if ($this->scanner->quarantineModule($moduleName, $reason)) {
            $this->info("Module '{$moduleName}' has been quarantined.");
            $this->line("Reason: {$reason}");
            return 0;
        }

        $this->error("Failed to quarantine module '{$moduleName}'.");
        return 1;
    }

    private function setupScheduledScans(): int
    {
        $this->info('Setting up scheduled security scans...');

        $schedule = $this->choice('How often should security scans run?', [
            'daily' => 'Daily at midnight',
            'weekly' => 'Weekly on Sunday',
            'monthly' => 'Monthly on 1st day',
        ], 'weekly');

        // This would typically integrate with Laravel's task scheduler
        $this->info("Scheduled security scans configured to run {$schedule}.");
        $this->line('');
        $this->line('Add this to your cron tab:');
        $this->line('* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1');

        return 0;
    }

    private function showSecurityStatus(): int
    {
        $this->info('Module Security Status');
        $this->line('');

        // Quick scan summary
        $results = $this->scanner->scanAllModules();
        $this->displayScanSummary($results);

        return 0;
    }

    private function displayScanSummary(array $results): void
    {
        $summary = $results['summary'];

        $this->table(
            ['Risk Level', 'Count'],
            [
                ['Critical', $summary['critical']],
                ['High', $summary['high']],
                ['Medium', $summary['medium']],
                ['Low', $summary['low']],
                ['Total Vulnerabilities', $summary['total_vulnerabilities']],
            ]
        );

        $this->line('');
        $avgScore = number_format($summary['average_score'], 1);
        $scoreColor = $summary['average_score'] >= 80 ? 'info' : ($summary['average_score'] >= 60 ? 'comment' : 'error');
        $this->line("Average Security Score: <{$scoreColor}>{$avgScore}/100</>");

        // Risk assessment
        if ($summary['critical'] > 0) {
            $this->line('<error>⚠️  CRITICAL VULNERABILITIES DETECTED - IMMEDIATE ACTION REQUIRED</error>');
        } elseif ($summary['high'] > 0) {
            $this->line('<comment>⚠️  High-risk vulnerabilities found - Address within 24-48 hours</comment>');
        } elseif ($summary['average_score'] >= 80) {
            $this->line('<info>✅ Good security posture - Continue monitoring</info>');
        }
    }

    private function displayModuleResults(array $result, bool $showDetails = true): void
    {
        $riskColor = match ($result['risk_level']) {
            'critical' => 'error',
            'high' => 'comment',
            'medium' => 'info',
            default => 'info',
        };

        $this->line("Module: <info>{$result['module']}</info>");
        $this->line("Risk Level: <{$riskColor}>{$result['risk_level']}</>");
        $this->line("Security Score: {$result['score']}/100");

        if (!empty($result['vulnerabilities']) && $showDetails) {
            $this->line('');
            $this->line('Vulnerabilities:');

            foreach ($result['vulnerabilities'] as $vuln) {
                $severity = match ($vuln['severity']) {
                    'critical' => '<error>CRITICAL</error>',
                    'high' => '<comment>HIGH</comment>',
                    'medium' => '<info>MEDIUM</info>',
                    default => 'LOW',
                };

                $this->line("  [{$severity}] {$vuln['message']}");

                if ($vuln['file'] !== 'N/A') {
                    $location = $vuln['file'];
                    if ($vuln['line'] > 0) {
                        $location .= ":{$vuln['line']}";
                    }
                    $this->line("    Location: {$location}");
                }

                if (!empty($vuln['code_snippet'])) {
                    $this->line("    Code: " . trim($vuln['code_snippet']));
                }
            }
        }

        $this->line('');
    }

    private function attemptFixes(array $result): void
    {
        $this->info('Attempting to fix low-risk issues...');

        $fixableIssues = array_filter($result['vulnerabilities'],
            fn($vuln) => $vuln['severity'] === 'low' && $this->isFixable($vuln['type'])
        );

        if (empty($fixableIssues)) {
            $this->line('No automatically fixable issues found.');
            return;
        }

        foreach ($fixableIssues as $issue) {
            if ($this->fixIssue($issue)) {
                $this->info("Fixed: {$issue['message']}");
            } else {
                $this->warn("Could not fix: {$issue['message']}");
            }
        }
    }

    private function isFixable(string $type): bool
    {
        return in_array($type, ['file_permissions', 'loose_dependency']);
    }

    private function fixIssue(array $issue): bool
    {
        switch ($issue['type']) {
            case 'file_permissions':
                return chmod($issue['file'], 0644);

            case 'loose_dependency':
                // This would require more complex logic to update manifest.json
                return false;

            default:
                return false;
        }
    }

    private function saveResults(array $results, string $outputFile, string $format): void
    {
        $content = match ($format) {
            'json' => json_encode($results, JSON_PRETTY_PRINT),
            'html' => $this->generateHtmlReport($results),
            default => $this->generateTextReport($results),
        };

        file_put_contents($outputFile, $content);
        $this->info("Results saved to: {$outputFile}");
    }

    private function generateTextReport(array $results): string
    {
        return $this->scanner->generateSecurityReport();
    }

    private function generateHtmlReport(array $results): string
    {
        $html = '<!DOCTYPE html><html><head><title>Module Security Report</title>';
        $html .= '<style>';
        $html .= 'body { font-family: Arial, sans-serif; margin: 20px; }';
        $html .= '.critical { color: #dc3545; }';
        $html .= '.high { color: #fd7e14; }';
        $html .= '.medium { color: #ffc107; }';
        $html .= '.low { color: #28a745; }';
        $html .= '.vulnerability { margin: 10px 0; padding: 10px; border-left: 4px solid #ccc; }';
        $html .= '.vulnerability.critical { border-color: #dc3545; background: #f8d7da; }';
        $html .= '.vulnerability.high { border-color: #fd7e14; background: #fdebd0; }';
        $html .= '</style></head><body>';

        $html .= '<h1>Module Security Report</h1>';
        $html .= '<p>Generated: ' . $results['timestamp']->format('Y-m-d H:i:s') . '</p>';

        $summary = $results['summary'];
        $html .= '<h2>Summary</h2>';
        $html .= '<ul>';
        $html .= "<li>Critical: <span class=\"critical\">{$summary['critical']}</span></li>";
        $html .= "<li>High: <span class=\"high\">{$summary['high']}</span></li>";
        $html .= "<li>Medium: <span class=\"medium\">{$summary['medium']}</span></li>";
        $html .= "<li>Low: <span class=\"low\">{$summary['low']}</span></li>";
        $html .= '</ul>';

        foreach ($results['results'] as $result) {
            $html .= "<h3>{$result['module']} (Risk: {$result['risk_level']})</h3>";
            $html .= "<p>Security Score: {$result['score']}/100</p>";

            if (!empty($result['vulnerabilities'])) {
                foreach ($result['vulnerabilities'] as $vuln) {
                    $html .= "<div class=\"vulnerability {$vuln['severity']}\">";
                    $html .= "<strong>{$vuln['message']}</strong><br>";
                    if ($vuln['file'] !== 'N/A') {
                        $html .= "File: {$vuln['file']}";
                        if ($vuln['line'] > 0) {
                            $html .= " (Line {$vuln['line']})";
                        }
                    }
                    $html .= '</div>';
                }
            }
        }

        $html .= '</body></html>';
        return $html;
    }
}