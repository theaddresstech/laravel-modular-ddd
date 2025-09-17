<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use ZipArchive;

class ModuleRestoreCommand extends Command
{
    protected $signature = 'module:restore
                            {backup-path : Path to the backup file or directory}
                            {--data : Restore database data}
                            {--force : Force restore without confirmation}
                            {--overwrite : Overwrite existing module}';
    protected $description = 'Restore a module from backup';

    public function __construct(
        private ModuleManagerInterface $moduleManager,
        private Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $backupPath = $this->argument('backup-path');

        if (!$this->files->exists($backupPath)) {
            $this->error("❌ Backup path not found: {$backupPath}");

            return self::FAILURE;
        }

        try {
            $this->info('🔄 Starting module restoration...');

            // Determine if backup is compressed
            $workingPath = $this->prepareBackup($backupPath);

            // Load backup manifest
            $manifest = $this->loadBackupManifest($workingPath);

            // Display restore information
            $this->displayRestoreInfo($manifest, $workingPath);

            // Confirm restoration
            if (!$this->option('force') && !$this->confirmRestore($manifest)) {
                $this->info('Restoration cancelled.');

                return self::SUCCESS;
            }

            // Perform restoration
            $result = $this->performRestore($manifest, $workingPath);

            // Cleanup temporary files if we extracted a zip
            if ($workingPath !== $backupPath) {
                $this->files->deleteDirectory($workingPath);
            }

            if ($result) {
                $this->info("✅ Module '{$manifest['module_name']}' restored successfully!");

                return self::SUCCESS;
            }
            $this->error('❌ Module restoration failed.');

            return self::FAILURE;
        } catch (Exception $e) {
            $this->error('❌ Restoration failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function prepareBackup(string $backupPath): string
    {
        // If it's a zip file, extract it
        if (pathinfo($backupPath, PATHINFO_EXTENSION) === 'zip') {
            $this->line('📦 Extracting compressed backup...');

            if (!class_exists('ZipArchive')) {
                throw new Exception('ZipArchive is required to restore compressed backups');
            }

            $extractPath = storage_path('app/temp-restore-' . uniqid());
            $this->files->makeDirectory($extractPath, 0o755, true);

            $zip = new ZipArchive();
            if ($zip->open($backupPath) === true) {
                $zip->extractTo($extractPath);
                $zip->close();
            } else {
                throw new Exception('Failed to extract backup archive');
            }

            return $extractPath;
        }

        return $backupPath;
    }

    private function loadBackupManifest(string $backupPath): array
    {
        $manifestPath = $backupPath . '/backup-manifest.json';

        if (!$this->files->exists($manifestPath)) {
            throw new Exception('Backup manifest not found. This may not be a valid backup.');
        }

        $content = $this->files->get($manifestPath);
        $manifest = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid backup manifest: ' . json_last_error_msg());
        }

        return $manifest;
    }

    private function displayRestoreInfo(array $manifest, string $backupPath): void
    {
        $this->newLine();
        $this->line('📋 <comment>Backup Information:</comment>');
        $this->line("   Module: {$manifest['module_name']}");
        $this->line("   Version: {$manifest['module_version']}");
        $this->line("   Created: {$manifest['backup_created']}");
        $this->line("   Files: {$manifest['files_count']}");

        if ($manifest['includes_database']) {
            $this->line('   <info>✓ Includes database data</info>');
        }

        // Check if module currently exists
        $existingModule = $this->moduleManager->getInfo($manifest['module_name']);
        if ($existingModule) {
            $this->newLine();
            $this->warn("⚠️  Module '{$manifest['module_name']}' already exists!");
            $this->line("   Current version: {$existingModule->version}");
            $this->line("   Backup version: {$manifest['module_version']}");

            if (!$this->option('overwrite')) {
                $this->error('Use --overwrite flag to replace existing module');

                throw new Exception('Module already exists');
            }
        }
    }

    private function confirmRestore(array $manifest): bool
    {
        $moduleName = $manifest['module_name'];
        $existingModule = $this->moduleManager->getInfo($moduleName);

        if ($existingModule) {
            return $this->confirm(
                "This will replace the existing '{$moduleName}' module. Continue?",
                false,
            );
        }

        return $this->confirm(
            "Do you want to restore module '{$moduleName}'?",
            true,
        );
    }

    private function performRestore(array $manifest, string $backupPath): bool
    {
        $moduleName = $manifest['module_name'];

        try {
            // Disable existing module if it exists
            $existingModule = $this->moduleManager->getInfo($moduleName);
            if ($existingModule && $existingModule->isEnabled()) {
                $this->line('🔄 Disabling existing module...');
                $this->moduleManager->disable($moduleName);
            }

            // Restore module files
            $this->line('📁 Restoring module files...');
            $this->restoreModuleFiles($manifest, $backupPath);

            // Restore database data if requested and available
            if ($this->option('data') && $manifest['includes_database']) {
                $this->line('🗄️  Restoring database data...');
                $this->restoreModuleData($backupPath);
            }

            // Install the restored module
            $this->line('📦 Installing restored module...');
            $this->moduleManager->install($moduleName);

            // Enable the module if it was previously enabled
            if ($existingModule && $existingModule->isEnabled()) {
                $this->line('✅ Enabling restored module...');
                $this->moduleManager->enable($moduleName);
            }

            // Clear caches
            $this->line('🧹 Clearing caches...');
            $this->call('module:cache', ['action' => 'clear']);

            return true;
        } catch (Exception $e) {
            $this->error('Restoration failed: ' . $e->getMessage());

            return false;
        }
    }

    private function restoreModuleFiles(array $manifest, string $backupPath): void
    {
        $moduleName = $manifest['module_name'];
        $modulesBasePath = config('modular-ddd.modules_path', base_path('modules'));
        $moduleDestination = "{$modulesBasePath}/{$moduleName}";
        $filesSource = "{$backupPath}/files";

        if (!$this->files->exists($filesSource)) {
            throw new Exception('Module files not found in backup');
        }

        // Remove existing module directory if it exists
        if ($this->files->exists($moduleDestination)) {
            $this->files->deleteDirectory($moduleDestination);
        }

        // Create parent directory if it doesn't exist
        if (!$this->files->exists($modulesBasePath)) {
            $this->files->makeDirectory($modulesBasePath, 0o755, true);
        }

        // Copy files from backup
        $this->files->copyDirectory($filesSource, $moduleDestination);

        // Verify restoration
        if (!$this->files->exists("{$moduleDestination}/manifest.json")) {
            throw new Exception('Module restoration verification failed - manifest.json not found');
        }
    }

    private function restoreModuleData(string $backupPath): void
    {
        $dataFile = "{$backupPath}/database/data.sql";

        if (!$this->files->exists($dataFile)) {
            $this->warn('   ⚠️  Database backup file not found, skipping data restoration');

            return;
        }

        try {
            $sql = $this->files->get($dataFile);

            // Split SQL statements and execute them
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                static fn ($stmt) => !empty($stmt) && !str_starts_with($stmt, '--'),
            );

            DB::beginTransaction();

            foreach ($statements as $statement) {
                if (!empty(trim($statement))) {
                    DB::unprepared($statement . ';');
                }
            }

            DB::commit();

            $this->line('   ✅ Database data restored successfully');
        } catch (Exception $e) {
            DB::rollBack();

            throw new Exception('Database restoration failed: ' . $e->getMessage());
        }
    }
}
