<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Tests\Feature;

use TaiCrm\LaravelModularDdd\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class ModuleCommandsTest extends TestCase
{
    public function test_module_list_command_shows_empty_list(): void
    {
        $exitCode = Artisan::call('module:list');

        $this->assertEquals(0, $exitCode);
        $this->assertStringContains('No modules found', Artisan::output());
    }

    public function test_module_list_command_shows_available_modules(): void
    {
        $this->createTestModule('TestModule', [
            'display_name' => 'Test Module',
            'version' => '1.0.0'
        ]);

        $exitCode = Artisan::call('module:list');

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContains('TestModule', $output);
        $this->assertStringContains('Test Module', $output);
        $this->assertStringContains('1.0.0', $output);
    }

    public function test_module_install_command_installs_module(): void
    {
        $this->createTestModule('TestModule');

        $exitCode = Artisan::call('module:install', ['name' => 'TestModule', '--force' => true]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContains('installed successfully', Artisan::output());
    }

    public function test_module_install_command_fails_for_missing_module(): void
    {
        $exitCode = Artisan::call('module:install', ['name' => 'NonexistentModule']);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContains('not found', Artisan::output());
    }

    public function test_module_enable_command_enables_installed_module(): void
    {
        $this->createTestModule('TestModule');

        // First install the module
        Artisan::call('module:install', ['name' => 'TestModule', '--force' => true]);

        // Then enable it
        $exitCode = Artisan::call('module:enable', ['name' => 'TestModule', '--force' => true]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContains('enabled successfully', Artisan::output());
    }

    public function test_module_disable_command_disables_enabled_module(): void
    {
        $this->createTestModule('TestModule');

        // Install and enable the module
        Artisan::call('module:install', ['name' => 'TestModule', '--force' => true]);
        Artisan::call('module:enable', ['name' => 'TestModule', '--force' => true]);

        // Then disable it
        $exitCode = Artisan::call('module:disable', ['name' => 'TestModule', '--force' => true]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContains('disabled successfully', Artisan::output());
    }

    public function test_module_remove_command_removes_installed_module(): void
    {
        $this->createTestModule('TestModule');

        // Install the module first
        Artisan::call('module:install', ['name' => 'TestModule', '--force' => true]);

        // Then remove it
        $exitCode = Artisan::call('module:remove', ['name' => 'TestModule', '--force' => true]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContains('removed successfully', Artisan::output());
    }

    public function test_module_status_command_shows_module_info(): void
    {
        $this->createTestModule('TestModule', [
            'display_name' => 'Test Module',
            'description' => 'A test module for testing',
            'version' => '2.0.0'
        ]);

        $exitCode = Artisan::call('module:status', ['name' => 'TestModule']);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContains('Test Module', $output);
        $this->assertStringContains('A test module for testing', $output);
        $this->assertStringContains('2.0.0', $output);
    }

    public function test_module_status_command_fails_for_missing_module(): void
    {
        $exitCode = Artisan::call('module:status', ['name' => 'NonexistentModule']);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContains('not found', Artisan::output());
    }

    public function test_module_make_command_creates_new_module(): void
    {
        $exitCode = Artisan::call('module:make', [
            'name' => 'NewModule',
            '--aggregate' => 'TestAggregate',
            '--author' => 'Test Author',
            '--description' => 'A new test module'
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContains('created successfully', Artisan::output());
        $this->assertModuleExists('NewModule');

        // Check if manifest was created correctly
        $manifestPath = $this->getTestModulesPath() . '/NewModule/manifest.json';
        $this->assertFileExists($manifestPath);

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $this->assertEquals('NewModule', $manifest['name']);
        $this->assertEquals('Test Author', $manifest['author']);
        $this->assertEquals('A new test module', $manifest['description']);
    }

    public function test_module_cache_clear_command(): void
    {
        $exitCode = Artisan::call('module:cache', ['action' => 'clear']);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContains('cache cleared', Artisan::output());
    }

    public function test_module_cache_rebuild_command(): void
    {
        $this->createTestModule('TestModule');

        $exitCode = Artisan::call('module:cache', ['action' => 'rebuild']);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContains('cache rebuilt', Artisan::output());
    }
}