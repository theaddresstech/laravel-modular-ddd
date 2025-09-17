<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Security;

use Exception;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;

class ModuleSecurityScanner
{
    private array $vulnerablePatterns;
    private array $sensitiveFiles;
    private array $maliciousFunctions;

    public function __construct(
        private ModuleManagerInterface $moduleManager,
        private Filesystem $filesystem,
        private LoggerInterface $logger,
    ) {
        $this->initializeSecurityPatterns();
    }

    public function scanAllModules(): array
    {
        $modules = $this->moduleManager->list();
        $results = [];

        foreach ($modules as $module) {
            $results[$module->name] = $this->scanModule($module);
        }

        return [
            'timestamp' => now(),
            'total_modules' => $modules->count(),
            'results' => $results,
            'summary' => $this->generateSummary($results),
        ];
    }

    public function scanModule(ModuleInfo $module): array
    {
        $this->logger->info("Starting security scan for module: {$module->name}");

        $result = [
            'module' => $module->name,
            'version' => $module->version,
            'path' => $module->path,
            'scanned_at' => now(),
            'vulnerabilities' => [],
            'warnings' => [],
            'score' => 100, // Start with perfect score
            'risk_level' => 'low',
        ];

        try {
            // File system security checks
            $result['vulnerabilities'] = array_merge(
                $result['vulnerabilities'],
                $this->scanForVulnerableCode($module),
                $this->scanForSensitiveFiles($module),
                $this->scanForMaliciousFunctions($module),
                $this->scanForConfigurationIssues($module),
            );

            // Dependency security checks
            $result['vulnerabilities'] = array_merge(
                $result['vulnerabilities'],
                $this->scanDependencies($module),
            );

            // Permission checks
            $result['vulnerabilities'] = array_merge(
                $result['vulnerabilities'],
                $this->scanFilePermissions($module),
            );

            // Manifest security validation
            $result['vulnerabilities'] = array_merge(
                $result['vulnerabilities'],
                $this->validateManifest($module),
            );

            // Calculate risk level and score
            $result['score'] = $this->calculateSecurityScore($result['vulnerabilities']);
            $result['risk_level'] = $this->determineRiskLevel($result['score']);
        } catch (Exception $e) {
            $result['vulnerabilities'][] = [
                'type' => 'scan_error',
                'severity' => 'high',
                'message' => 'Failed to complete security scan: ' . $e->getMessage(),
                'file' => 'N/A',
                'line' => 0,
            ];

            $this->logger->error("Security scan failed for module {$module->name}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $result;
    }

    public function validateModuleSignature(ModuleInfo $module): bool
    {
        if (!config('modular-ddd.security.signature_verification', false)) {
            return true; // Skip validation if disabled
        }

        $signatureFile = $module->path . '/module.sig';
        if (!$this->filesystem->exists($signatureFile)) {
            $this->logger->warning("Module signature file missing: {$module->name}");

            return false;
        }

        try {
            $signature = $this->filesystem->get($signatureFile);
            $moduleHash = $this->calculateModuleHash($module);

            return $this->verifySignature($moduleHash, $signature);
        } catch (Exception $e) {
            $this->logger->error("Signature verification failed for module {$module->name}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function quarantineModule(string $moduleName, string $reason): bool
    {
        try {
            $module = $this->moduleManager->list()->firstWhere('name', $moduleName);
            if (!$module) {
                throw new InvalidArgumentException("Module '{$moduleName}' not found");
            }

            $quarantinePath = storage_path('app/quarantine');
            $this->filesystem->ensureDirectoryExists($quarantinePath);

            $timestamp = now()->format('Y-m-d_H-i-s');
            $quarantineDir = "{$quarantinePath}/{$moduleName}_{$timestamp}";

            // Move module to quarantine
            $this->filesystem->moveDirectory($module->path, $quarantineDir);

            // Create quarantine record
            $quarantineRecord = [
                'module_name' => $moduleName,
                'original_path' => $module->path,
                'quarantine_path' => $quarantineDir,
                'reason' => $reason,
                'quarantined_at' => now(),
                'quarantined_by' => 'security_scanner',
            ];

            $this->filesystem->put(
                "{$quarantineDir}/quarantine.json",
                json_encode($quarantineRecord, JSON_PRETTY_PRINT),
            );

            $this->logger->critical('Module quarantined', $quarantineRecord);

            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to quarantine module {$moduleName}", [
                'error' => $e->getMessage(),
                'reason' => $reason,
            ]);

            return false;
        }
    }

    public function generateSecurityReport(): string
    {
        $scanResults = $this->scanAllModules();

        $report = "# Module Security Report\n\n";
        $report .= "Generated: {$scanResults['timestamp']->format('Y-m-d H:i:s')}\n";
        $report .= "Total Modules Scanned: {$scanResults['total_modules']}\n\n";

        // Summary
        $summary = $scanResults['summary'];
        $report .= "## Executive Summary\n\n";
        $report .= "- **Critical Vulnerabilities**: {$summary['critical']}\n";
        $report .= "- **High Risk Vulnerabilities**: {$summary['high']}\n";
        $report .= "- **Medium Risk Vulnerabilities**: {$summary['medium']}\n";
        $report .= "- **Low Risk Vulnerabilities**: {$summary['low']}\n";
        $report .= '- **Average Security Score**: ' . number_format($summary['average_score'], 1) . "/100\n\n";

        // High-risk modules
        $highRiskModules = array_filter(
            $scanResults['results'],
            static fn ($result) => $result['risk_level'] === 'critical' || $result['risk_level'] === 'high',
        );

        if (!empty($highRiskModules)) {
            $report .= "## High-Risk Modules\n\n";
            foreach ($highRiskModules as $module) {
                $report .= "### {$module['module']} (Risk Level: {$module['risk_level']})\n\n";
                $report .= "Score: {$module['score']}/100\n\n";

                if (!empty($module['vulnerabilities'])) {
                    $report .= "**Vulnerabilities:**\n";
                    foreach ($module['vulnerabilities'] as $vuln) {
                        $report .= "- **{$vuln['severity']}**: {$vuln['message']}\n";
                        if ($vuln['file'] !== 'N/A') {
                            $report .= "  - File: `{$vuln['file']}`";
                            if ($vuln['line'] > 0) {
                                $report .= " (Line {$vuln['line']})";
                            }
                            $report .= "\n";
                        }
                    }
                }
                $report .= "\n";
            }
        }

        // Recommendations
        $report .= "## Recommendations\n\n";
        $report .= $this->generateRecommendations($scanResults);

        return $report;
    }

    private function initializeSecurityPatterns(): void
    {
        $this->vulnerablePatterns = [
            // Code injection patterns
            '/eval\s*\(/i' => 'Potential code injection via eval()',
            '/exec\s*\(/i' => 'Command execution via exec()',
            '/system\s*\(/i' => 'Command execution via system()',
            '/shell_exec\s*\(/i' => 'Command execution via shell_exec()',
            '/passthru\s*\(/i' => 'Command execution via passthru()',
            '/proc_open\s*\(/i' => 'Process execution via proc_open()',

            // SQL injection patterns
            '/\$_(GET|POST|REQUEST)\[.*?\].*?(SELECT|INSERT|UPDATE|DELETE)/i' => 'Potential SQL injection',
            '/mysql_query\s*\(\s*[\'"][^\'\"]*\$.*?[\'"]\s*\)/i' => 'SQL query with potential injection',

            // File inclusion vulnerabilities
            '/include\s*\(\s*\$_(GET|POST|REQUEST)/i' => 'Dynamic file inclusion vulnerability',
            '/require\s*\(\s*\$_(GET|POST|REQUEST)/i' => 'Dynamic file requirement vulnerability',

            // Crypto weaknesses
            '/md5\s*\(/i' => 'Weak cryptographic hash (MD5)',
            '/sha1\s*\(/i' => 'Weak cryptographic hash (SHA1)',
            '/mcrypt_/i' => 'Deprecated mcrypt functions',

            // Information disclosure
            '/phpinfo\s*\(/i' => 'Information disclosure via phpinfo()',
            '/var_dump\s*\(/i' => 'Potential information disclosure via var_dump()',
            '/print_r\s*\(/i' => 'Potential information disclosure via print_r()',

            // Deserialization vulnerabilities
            '/unserialize\s*\(\s*\$_(GET|POST|REQUEST)/i' => 'Unsafe deserialization from user input',
        ];

        $this->sensitiveFiles = [
            '.env' => 'Environment configuration file',
            'config/database.php' => 'Database configuration',
            'config/mail.php' => 'Mail configuration',
            'storage/logs/' => 'Log files may contain sensitive data',
            'id_rsa' => 'Private SSH key',
            'id_dsa' => 'Private SSH key',
            '.ssh/' => 'SSH configuration directory',
            'composer.json' => 'Dependency information',
            'package.json' => 'Node.js dependencies',
        ];

        $this->maliciousFunctions = [
            'base64_decode' => 'Potential obfuscation technique',
            'gzinflate' => 'Potential obfuscation technique',
            'str_rot13' => 'Potential obfuscation technique',
            'create_function' => 'Dynamic function creation (deprecated)',
            'call_user_func' => 'Dynamic function calling',
            'call_user_func_array' => 'Dynamic function calling with array',
        ];
    }

    private function scanForVulnerableCode(ModuleInfo $module): array
    {
        $vulnerabilities = [];
        $phpFiles = $this->getPhpFiles($module->path);

        foreach ($phpFiles as $file) {
            $content = $this->filesystem->get($file);
            $lines = explode("\n", $content);

            foreach ($this->vulnerablePatterns as $pattern => $description) {
                if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    $line = substr_count(substr($content, 0, $matches[0][1]), "\n") + 1;

                    $vulnerabilities[] = [
                        'type' => 'vulnerable_code',
                        'severity' => $this->getSeverityForPattern($pattern),
                        'message' => $description,
                        'file' => $file,
                        'line' => $line,
                        'code_snippet' => $lines[$line - 1] ?? '',
                    ];
                }
            }
        }

        return $vulnerabilities;
    }

    private function scanForSensitiveFiles(ModuleInfo $module): array
    {
        $vulnerabilities = [];

        foreach ($this->sensitiveFiles as $pattern => $description) {
            $files = $this->filesystem->glob($module->path . '/**/' . $pattern);

            foreach ($files as $file) {
                // Skip legitimate files in appropriate locations
                if ($this->isLegitimateLocation($file, $pattern)) {
                    continue;
                }

                $vulnerabilities[] = [
                    'type' => 'sensitive_file',
                    'severity' => 'medium',
                    'message' => "Sensitive file detected: {$description}",
                    'file' => $file,
                    'line' => 0,
                ];
            }
        }

        return $vulnerabilities;
    }

    private function scanForMaliciousFunctions(ModuleInfo $module): array
    {
        $vulnerabilities = [];
        $phpFiles = $this->getPhpFiles($module->path);

        foreach ($phpFiles as $file) {
            $content = $this->filesystem->get($file);

            foreach ($this->maliciousFunctions as $function => $description) {
                if (preg_match_all('/\b' . preg_quote($function) . '\s*\(/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;

                        $vulnerabilities[] = [
                            'type' => 'suspicious_function',
                            'severity' => 'medium',
                            'message' => "Suspicious function usage: {$function} - {$description}",
                            'file' => $file,
                            'line' => $line,
                        ];
                    }
                }
            }
        }

        return $vulnerabilities;
    }

    private function scanForConfigurationIssues(ModuleInfo $module): array
    {
        $vulnerabilities = [];

        // Check for debug mode in production
        $envFile = $module->path . '/.env';
        if ($this->filesystem->exists($envFile)) {
            $envContent = $this->filesystem->get($envFile);
            if (preg_match('/APP_DEBUG\s*=\s*true/i', $envContent)) {
                $vulnerabilities[] = [
                    'type' => 'configuration',
                    'severity' => 'high',
                    'message' => 'Debug mode enabled in .env file',
                    'file' => $envFile,
                    'line' => 0,
                ];
            }
        }

        // Check for default passwords or keys
        $configFiles = $this->filesystem->glob($module->path . '/Config/*.php');
        foreach ($configFiles as $configFile) {
            $content = $this->filesystem->get($configFile);

            if (preg_match('/(password|secret|key).*?[\'"](?:password|secret|123|admin|default)[\'"]/', $content, $matches)) {
                $vulnerabilities[] = [
                    'type' => 'configuration',
                    'severity' => 'high',
                    'message' => 'Default or weak credentials detected in configuration',
                    'file' => $configFile,
                    'line' => 0,
                ];
            }
        }

        return $vulnerabilities;
    }

    private function scanDependencies(ModuleInfo $module): array
    {
        $vulnerabilities = [];

        foreach ($module->dependencies as $dependency) {
            // Check for known vulnerable packages (simplified check)
            if ($this->isVulnerablePackage($dependency['name'], $dependency['constraint'] ?? '*')) {
                $vulnerabilities[] = [
                    'type' => 'vulnerable_dependency',
                    'severity' => 'high',
                    'message' => "Vulnerable dependency: {$dependency['name']}",
                    'file' => $module->path . '/manifest.json',
                    'line' => 0,
                ];
            }

            // Check for outdated dependencies
            if ($this->isOutdatedDependency($dependency['name'], $dependency['constraint'] ?? '*')) {
                $vulnerabilities[] = [
                    'type' => 'outdated_dependency',
                    'severity' => 'medium',
                    'message' => "Outdated dependency: {$dependency['name']}",
                    'file' => $module->path . '/manifest.json',
                    'line' => 0,
                ];
            }
        }

        return $vulnerabilities;
    }

    private function scanFilePermissions(ModuleInfo $module): array
    {
        $vulnerabilities = [];

        // Check for world-writable files
        $files = $this->getAllFiles($module->path);
        foreach ($files as $file) {
            $perms = fileperms($file);
            if ($perms & 0o002) { // World writable
                $vulnerabilities[] = [
                    'type' => 'file_permissions',
                    'severity' => 'medium',
                    'message' => 'World-writable file detected',
                    'file' => $file,
                    'line' => 0,
                ];
            }
        }

        return $vulnerabilities;
    }

    private function validateManifest(ModuleInfo $module): array
    {
        $vulnerabilities = [];
        $manifestPath = $module->path . '/manifest.json';

        if (!$this->filesystem->exists($manifestPath)) {
            $vulnerabilities[] = [
                'type' => 'missing_manifest',
                'severity' => 'high',
                'message' => 'Module manifest file missing',
                'file' => $manifestPath,
                'line' => 0,
            ];

            return $vulnerabilities;
        }

        try {
            $manifest = json_decode($this->filesystem->get($manifestPath), true);

            // Check for required security fields
            if (!isset($manifest['security'])) {
                $vulnerabilities[] = [
                    'type' => 'manifest_security',
                    'severity' => 'medium',
                    'message' => 'Missing security section in manifest',
                    'file' => $manifestPath,
                    'line' => 0,
                ];
            }

            // Check for proper version constraints
            if (isset($manifest['dependencies'])) {
                foreach ($manifest['dependencies'] as $dep) {
                    if (!isset($dep['constraint']) || $dep['constraint'] === '*') {
                        $vulnerabilities[] = [
                            'type' => 'loose_dependency',
                            'severity' => 'low',
                            'message' => "Loose dependency constraint for {$dep['name']}",
                            'file' => $manifestPath,
                            'line' => 0,
                        ];
                    }
                }
            }
        } catch (JsonException $e) {
            $vulnerabilities[] = [
                'type' => 'invalid_manifest',
                'severity' => 'high',
                'message' => 'Invalid JSON in manifest file',
                'file' => $manifestPath,
                'line' => 0,
            ];
        }

        return $vulnerabilities;
    }

    private function calculateModuleHash(ModuleInfo $module): string
    {
        $files = $this->getAllFiles($module->path);
        $hashes = [];

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $hashes[] = hash_file('sha256', $file);
            }
        }

        sort($hashes);

        return hash('sha256', implode('', $hashes));
    }

    private function verifySignature(string $hash, string $signature): bool
    {
        // Implementation would depend on your signing mechanism
        // This is a simplified version
        $publicKey = config('modular-ddd.security.public_key');
        if (!$publicKey) {
            return false;
        }

        return openssl_verify($hash, base64_decode($signature), $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    private function calculateSecurityScore(array $vulnerabilities): int
    {
        $score = 100;

        foreach ($vulnerabilities as $vuln) {
            $penalty = match ($vuln['severity']) {
                'critical' => 30,
                'high' => 20,
                'medium' => 10,
                'low' => 5,
                default => 5,
            };

            $score -= $penalty;
        }

        return max(0, $score);
    }

    private function determineRiskLevel(int $score): string
    {
        return match (true) {
            $score >= 90 => 'low',
            $score >= 70 => 'medium',
            $score >= 50 => 'high',
            default => 'critical',
        };
    }

    private function generateSummary(array $results): array
    {
        $summary = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'total_vulnerabilities' => 0,
            'average_score' => 0,
        ];

        $totalScore = 0;
        $moduleCount = 0;

        foreach ($results as $result) {
            $moduleCount++;
            $totalScore += $result['score'];

            foreach ($result['vulnerabilities'] as $vuln) {
                $summary[$vuln['severity']]++;
                $summary['total_vulnerabilities']++;
            }
        }

        if ($moduleCount > 0) {
            $summary['average_score'] = $totalScore / $moduleCount;
        }

        return $summary;
    }

    private function generateRecommendations(array $scanResults): string
    {
        $recommendations = [];

        $summary = $scanResults['summary'];

        if ($summary['critical'] > 0) {
            $recommendations[] = "🚨 **IMMEDIATE ACTION REQUIRED**: {$summary['critical']} critical vulnerabilities found. Quarantine affected modules immediately.";
        }

        if ($summary['high'] > 0) {
            $recommendations[] = "⚠️  **HIGH PRIORITY**: Address {$summary['high']} high-severity vulnerabilities within 24-48 hours.";
        }

        if ($summary['average_score'] < 70) {
            $recommendations[] = '📊 **SECURITY POSTURE**: Average security score is ' . number_format($summary['average_score'], 1) . '/100. Consider implementing additional security measures.';
        }

        $recommendations[] = '🔍 **REGULAR SCANNING**: Schedule automated security scans to run weekly.';
        $recommendations[] = '📝 **CODE REVIEW**: Implement mandatory security code reviews for all module changes.';
        $recommendations[] = '🔐 **ACCESS CONTROL**: Restrict module installation to authorized personnel only.';
        $recommendations[] = '📚 **TRAINING**: Provide security awareness training for developers.';

        return implode("\n", $recommendations) . "\n";
    }

    private function getPhpFiles(string $path): array
    {
        return $this->filesystem->glob($path . '/**/*.php');
    }

    private function getAllFiles(string $path): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function getSeverityForPattern(string $pattern): string
    {
        // Map patterns to severity levels
        $criticalPatterns = ['/eval\s*\(/i', '/exec\s*\(/i', '/system\s*\(/i'];
        $highPatterns = ['/\$_(GET|POST|REQUEST)\[.*?\].*?(SELECT|INSERT|UPDATE|DELETE)/i'];

        if (in_array($pattern, $criticalPatterns)) {
            return 'critical';
        }

        if (in_array($pattern, $highPatterns)) {
            return 'high';
        }

        return 'medium';
    }

    private function isLegitimateLocation(string $file, string $pattern): bool
    {
        // Define legitimate locations for sensitive files
        $legitimatePaths = [
            '.env' => ['/config/', '/tests/', '/stubs/'],
            'composer.json' => ['/'],  // Root level is OK
        ];

        if (isset($legitimatePaths[$pattern])) {
            foreach ($legitimatePaths[$pattern] as $allowedPath) {
                if (str_contains($file, $allowedPath)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isVulnerablePackage(string $package, string $constraint): bool
    {
        // This would typically check against a vulnerability database
        // Simplified implementation for demonstration
        $knownVulnerable = [
            'example/vulnerable-package' => ['<1.0.0'],
        ];

        if (isset($knownVulnerable[$package])) {
            foreach ($knownVulnerable[$package] as $vulnConstraint) {
                if ($this->satisfiesConstraint($constraint, $vulnConstraint)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isOutdatedDependency(string $package, string $constraint): bool
    {
        // This would typically check against latest available versions
        // Simplified implementation
        return $constraint === '*' || !str_contains($constraint, '^');
    }

    private function satisfiesConstraint(string $version, string $constraint): bool
    {
        // Simplified constraint checking
        return version_compare($version, ltrim($constraint, '<>=^~'), '>=');
    }
}
