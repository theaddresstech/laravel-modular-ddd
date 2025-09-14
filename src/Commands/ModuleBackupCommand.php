<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\Exceptions\ModuleNotFoundException;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

class ModuleBackupCommand extends Command
{
    protected $signature = 'module:backup
                            {module? : The module to backup}
                            {--all : Backup all enabled modules}
                            {--data : Include database data in backup}
                            {--path= : Custom backup path}
                            {--compress : Compress backup files}';

    protected $description = 'Create a backup of module files and optionally database data';

    public function __construct(
        private ModuleManagerInterface $moduleManager,
        private Filesystem $files
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->backupAllModules();
        }

        $moduleName = $this->argument('module');
        if (!$moduleName) {
            $this->error('Please specify a module name or use --all flag');
            return self::FAILURE;
        }

        return $this->backupModule($moduleName);
    }

    private function backupAllModules(): int
    {
        $modules = $this->moduleManager->list()
            ->filter(fn($module) => $module->isEnabled());

        if ($modules->isEmpty()) {
            $this->info('No enabled modules found.');
            return self::SUCCESS;
        }

        $this->info('💾 Creating backup for all enabled modules...');
        $success = true;
        $backupInfo = [];

        foreach ($modules as $module) {
            $this->newLine();
            $this->line("📦 Backing up module: {$module->name}");

            $result = $this->performModuleBackup($module->name);
            if ($result['success']) {
                $backupInfo[] = $result;
            } else {
                $success = false;
            }
        }

        if ($success && !empty($backupInfo)) {
            $this->displayBackupSummary($backupInfo);
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }

    private function backupModule(string $moduleName): int
    {
        try {
            $module = $this->moduleManager->getInfo($moduleName);

            if (!$module) {
                throw new ModuleNotFoundException($moduleName);
            }

            $this->info("💾 Creating backup for module: {$moduleName}");

            $result = $this->performModuleBackup($moduleName);

            if ($result['success']) {
                $this->displayBackupInfo($result);
                return self::SUCCESS;
            } else {
                $this->error("❌ Backup failed for module '{$moduleName}'");
                return self::FAILURE;
            }

        } catch (ModuleNotFoundException $e) {
            $this->error("❌ " . $e->getMessage());
            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error("❌ Backup failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function performModuleBackup(string $moduleName): array
    {
        try {
            $module = $this->moduleManager->getInfo($moduleName);
            $timestamp = now()->format('Y-m-d_H-i-s');

            $backupPath = $this->option('path') ?? storage_path('app/module-backups');
            $moduleBackupPath = "{$backupPath}/{$moduleName}/{$timestamp}";

            if (!$this->files->exists($moduleBackupPath)) {
                $this->files->makeDirectory($moduleBackupPath, 0755, true);
            }

            $backupInfo = [
                'module' => $moduleName,
                'timestamp' => $timestamp,
                'path' => $moduleBackupPath,
                'files_backed_up' => 0,
                'size' => 0,
                'includes_data' => false,
                'compressed' => false,
                'success' => false,
            ];

            // Backup module files
            $this->line("   📁 Backing up module files...");
            $filesPath = "{$moduleBackupPath}/files";
            $this->files->makeDirectory($filesPath, 0755, true);
            $this->files->copyDirectory($module->path, $filesPath);

            $backupInfo['files_backed_up'] = $this->countFiles($filesPath);

            // Create manifest with backup metadata
            $manifest = [
                'module_name' => $moduleName,
                'module_version' => $module->version,
                'backup_created' => now()->toISOString(),
                'laravel_version' => app()->version(),
                'package_version' => '1.0.0', // Would be dynamic in real implementation
                'includes_database' => $this->option('data'),
                'files_count' => $backupInfo['files_backed_up'],
            ];

            $this->files->put(
                "{$moduleBackupPath}/backup-manifest.json",
                json_encode($manifest, JSON_PRETTY_PRINT)
            );

            // Backup database data if requested
            if ($this->option('data')) {
                $this->line("   🗄️  Backing up module database data...");
                $this->backupModuleData($moduleName, $moduleBackupPath);
                $backupInfo['includes_data'] = true;
            }

            // Calculate backup size
            $backupInfo['size'] = $this->getDirectorySize($moduleBackupPath);

            // Compress backup if requested
            if ($this->option('compress')) {
                $this->line("   🗜️  Compressing backup...");
                $compressedPath = $this->compressBackup($moduleBackupPath);
                if ($compressedPath) {
                    $backupInfo['compressed'] = true;
                    $backupInfo['compressed_path'] = $compressedPath;
                    $backupInfo['compressed_size'] = filesize($compressedPath);
                }
            }

            $backupInfo['success'] = true;
            $this->line("   ✅ Backup completed successfully");

            return $backupInfo;

        } catch (\Exception $e) {
            return [
                'module' => $moduleName,
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function backupModuleData(string $moduleName, string $backupPath): void
    {
        $dataPath = "{$backupPath}/database";
        $this->files->makeDirectory($dataPath, 0755, true);

        // Get module tables (this is a simplified approach)
        // In a real implementation, you would need to identify module-specific tables
        $modulePrefix = strtolower($moduleName);
        $tables = $this->getModuleTables($modulePrefix);

        $sqlDump = "-- Module: {$moduleName}\n-- Backup created: " . now()->toISOString() . "\n\n";

        foreach ($tables as $table) {
            try {
                $data = DB::table($table)->get();

                if ($data->isNotEmpty()) {
                    $sqlDump .= "-- Table: {$table}\n";
                    $sqlDump .= "TRUNCATE TABLE `{$table}`;\n";

                    foreach ($data as $row) {
                        $values = array_map(function ($value) {
                            return is_null($value) ? 'NULL' : "'" . addslashes($value) . "'";
                        }, (array) $row);

                        $columns = implode('`, `', array_keys((array) $row));
                        $values = implode(', ', $values);

                        $sqlDump .= "INSERT INTO `{$table}` (`{$columns}`) VALUES ({$values});\n";
                    }

                    $sqlDump .= "\n";
                }
            } catch (\Exception $e) {
                $sqlDump .= "-- Error backing up table {$table}: " . $e->getMessage() . "\n\n";
            }
        }

        $this->files->put("{$dataPath}/data.sql", $sqlDump);
    }

    private function getModuleTables(string $modulePrefix): array
    {
        // This is a simplified implementation
        // In practice, you might need a more sophisticated way to identify module tables
        $tables = DB::select("SHOW TABLES LIKE '{$modulePrefix}_%'");

        return array_map(function ($table) {
            return array_values((array) $table)[0];
        }, $tables);
    }

    private function countFiles(string $directory): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        return $count;
    }

    private function getDirectorySize(string $directory): int
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    private function compressBackup(string $backupPath): ?string
    {
        if (!class_exists('ZipArchive')) {
            $this->warn("   ⚠️  ZipArchive not available, skipping compression");
            return null;
        }

        $zipPath = $backupPath . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->warn("   ⚠️  Could not create zip file, skipping compression");
            return null;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($backupPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($backupPath . '/', '', $file->getRealPath());
                $zip->addFile($file->getRealPath(), $relativePath);
            } elseif ($file->isDir()) {
                $relativePath = str_replace($backupPath . '/', '', $file->getRealPath()) . '/';
                $zip->addEmptyDir($relativePath);
            }
        }

        $zip->close();

        // Remove uncompressed directory
        $this->files->deleteDirectory($backupPath);

        return $zipPath;
    }

    private function displayBackupInfo(array $info): void
    {
        $this->newLine();
        $this->line("📋 <comment>Backup Information:</comment>");
        $this->line("   Module: {$info['module']}");
        $this->line("   Timestamp: {$info['timestamp']}");
        $this->line("   Location: {$info['path']}");
        $this->line("   Files: {$info['files_backed_up']}");
        $this->line("   Size: " . $this->formatBytes($info['size']));

        if ($info['includes_data']) {
            $this->line("   <info>✓ Database data included</info>");
        }

        if ($info['compressed']) {
            $this->line("   <info>✓ Compressed</info> (Size: " . $this->formatBytes($info['compressed_size']) . ")");
        }
    }

    private function displayBackupSummary(array $backups): void
    {
        $this->newLine();
        $this->line("📊 <comment>Backup Summary:</comment>");
        $this->line("   Total modules backed up: " . count($backups));

        $totalSize = array_sum(array_column($backups, 'size'));
        $this->line("   Total size: " . $this->formatBytes($totalSize));

        $withData = count(array_filter($backups, fn($b) => $b['includes_data']));
        if ($withData > 0) {
            $this->line("   Modules with data: {$withData}");
        }

        $compressed = count(array_filter($backups, fn($b) => $b['compressed']));
        if ($compressed > 0) {
            $this->line("   Compressed backups: {$compressed}");
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
}