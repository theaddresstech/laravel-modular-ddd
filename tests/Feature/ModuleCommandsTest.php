<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use TaiCrm\LaravelModularDdd\Tests\TestCase;

class ModuleCommandsTest extends TestCase
{
    public function testModuleListCommandShowsEmptyList(): void
    {
        $exitCode = Artisan::call('module:list');

        $this->assertSame(0, $exitCode);
        $this->assertStringContains('No modules found', Artisan::output());
    }

    public function testModuleListCommandShowsAvailableModules(): void
    {
        $this->createTestModule('TestModule', [
            'display_name' => 'Test Module',
            'version' => '1.0.0',
        ]);

        $exitCode = Artisan::call('module:list');

        $this->assertSame(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContains('TestModule', $output);
        $this->assertStringContains('Test Module', $output);
        $this->assertStringContains('1.0.0', $output);
    }

    public function testModuleInstallCommandInstallsModule(): void
    {
        $this->createTestModule('TestModule');

        $exitCode = Artisan::call('module:install', ['name' => 'TestModule', '--force' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContains('installed successfully', Artisan::output());
    }

    public function testModuleInstallCommandFailsForMissingModule(): void
    {
        $exitCode = Artisan::call('module:install', ['name' => 'NonexistentModule']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContains('not found', Artisan::output());
    }

    public function testModuleEnableCommandEnablesInstalledModule(): void
    {
        $this->createTestModule('TestModule');

        // First install the module
        Artisan::call('module:install', ['name' => 'TestModule', '--force' => true]);

        // Then enable it
        $exitCode = Artisan::call('module:enable', ['name' => 'TestModule', '--force' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContains('enabled successfully', Artisan::output());
    }

    public function testModuleDisableCommandDisablesEnabledModule(): void
    {
        $this->createTestModule('TestModule');

        // Install and enable the module
        Artisan::call('module:install', ['name' => 'TestModule', '--force' => true]);
        Artisan::call('module:enable', ['name' => 'TestModule', '--force' => true]);

        // Then disable it
        $exitCode = Artisan::call('module:disable', ['name' => 'TestModule', '--force' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContains('disabled successfully', Artisan::output());
    }

    public function testModuleRemoveCommandRemovesInstalledModule(): void
    {
        $this->createTestModule('TestModule');

        // Install the module first
        Artisan::call('module:install', ['name' => 'TestModule', '--force' => true]);

        // Then remove it
        $exitCode = Artisan::call('module:remove', ['name' => 'TestModule', '--force' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContains('removed successfully', Artisan::output());
    }

    public function testModuleStatusCommandShowsModuleInfo(): void
    {
        $this->createTestModule('TestModule', [
            'display_name' => 'Test Module',
            'description' => 'A test module for testing',
            'version' => '2.0.0',
        ]);

        $exitCode = Artisan::call('module:status', ['name' => 'TestModule']);

        $this->assertSame(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContains('Test Module', $output);
        $this->assertStringContains('A test module for testing', $output);
        $this->assertStringContains('2.0.0', $output);
    }

    public function testModuleStatusCommandFailsForMissingModule(): void
    {
        $exitCode = Artisan::call('module:status', ['name' => 'NonexistentModule']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContains('not found', Artisan::output());
    }

    public function testModuleMakeCommandCreatesNewModule(): void
    {
        $exitCode = Artisan::call('module:make', [
            'name' => 'NewModule',
            '--aggregate' => 'TestAggregate',
            '--author' => 'Test Author',
            '--description' => 'A new test module',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContains('created successfully', Artisan::output());
        $this->assertModuleExists('NewModule');

        // Check if manifest was created correctly
        $manifestPath = $this->getTestModulesPath() . '/NewModule/manifest.json';
        $this->assertFileExists($manifestPath);

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $this->assertSame('NewModule', $manifest['name']);
        $this->assertSame('Test Author', $manifest['author']);
        $this->assertSame('A new test module', $manifest['description']);
    }

    public function testModuleCacheClearCommand(): void
    {
        $exitCode = Artisan::call('module:cache', ['action' => 'clear']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContains('cache cleared', Artisan::output());
    }

    public function testModuleCacheRebuildCommand(): void
    {
        $this->createTestModule('TestModule');

        $exitCode = Artisan::call('module:cache', ['action' => 'rebuild']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContains('cache rebuilt', Artisan::output());
    }
}
