<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Tests\Unit;

use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleState;
use TaiCrm\LaravelModularDdd\Exceptions\ModuleNotFoundException;
use TaiCrm\LaravelModularDdd\Exceptions\DependencyException;
use TaiCrm\LaravelModularDdd\Tests\TestCase;

class ModuleManagerTest extends TestCase
{
    private ModuleManagerInterface $moduleManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->moduleManager = $this->app->make(ModuleManagerInterface::class);
    }

    public function test_list_returns_empty_collection_when_no_modules(): void
    {
        $modules = $this->moduleManager->list();

        $this->assertEmpty($modules);
    }

    public function test_list_returns_discovered_modules(): void
    {
        $this->createTestModule('TestModule');
        $this->createTestModule('AnotherModule');

        $modules = $this->moduleManager->list();

        $this->assertCount(2, $modules);
        $this->assertTrue($modules->has('TestModule'));
        $this->assertTrue($modules->has('AnotherModule'));
    }

    public function test_can_install_module(): void
    {
        $this->createTestModule('TestModule');

        $result = $this->moduleManager->install('TestModule');

        $this->assertTrue($result);
        $this->assertTrue($this->moduleManager->isInstalled('TestModule'));
        $this->assertEquals(ModuleState::Installed, $this->moduleManager->getState('TestModule'));
    }

    public function test_cannot_install_nonexistent_module(): void
    {
        $this->expectException(ModuleNotFoundException::class);

        $this->moduleManager->install('NonexistentModule');
    }

    public function test_can_enable_installed_module(): void
    {
        $this->createTestModule('TestModule');
        $this->moduleManager->install('TestModule');

        $result = $this->moduleManager->enable('TestModule');

        $this->assertTrue($result);
        $this->assertTrue($this->moduleManager->isEnabled('TestModule'));
        $this->assertEquals(ModuleState::Enabled, $this->moduleManager->getState('TestModule'));
    }

    public function test_can_disable_enabled_module(): void
    {
        $this->createTestModule('TestModule');
        $this->moduleManager->install('TestModule');
        $this->moduleManager->enable('TestModule');

        $result = $this->moduleManager->disable('TestModule');

        $this->assertTrue($result);
        $this->assertFalse($this->moduleManager->isEnabled('TestModule'));
        $this->assertEquals(ModuleState::Disabled, $this->moduleManager->getState('TestModule'));
    }

    public function test_can_remove_installed_module(): void
    {
        $this->createTestModule('TestModule');
        $this->moduleManager->install('TestModule');

        $result = $this->moduleManager->remove('TestModule');

        $this->assertTrue($result);
        $this->assertFalse($this->moduleManager->isInstalled('TestModule'));
        $this->assertEquals(ModuleState::NotInstalled, $this->moduleManager->getState('TestModule'));
    }

    public function test_validates_dependencies_before_installation(): void
    {
        $this->createTestModule('DependentModule', [
            'dependencies' => ['MissingModule']
        ]);

        $this->expectException(DependencyException::class);
        $this->expectExceptionMessage('missing dependencies');

        $this->moduleManager->install('DependentModule');
    }

    public function test_installs_dependencies_automatically(): void
    {
        $this->createTestModule('DependencyModule');
        $this->createTestModule('MainModule', [
            'dependencies' => ['DependencyModule']
        ]);

        $result = $this->moduleManager->install('MainModule');

        $this->assertTrue($result);
        $this->assertTrue($this->moduleManager->isInstalled('DependencyModule'));
        $this->assertTrue($this->moduleManager->isInstalled('MainModule'));
    }

    public function test_gets_module_dependencies(): void
    {
        $this->createTestModule('TestModule', [
            'dependencies' => ['Dependency1', 'Dependency2']
        ]);

        $dependencies = $this->moduleManager->getDependencies('TestModule');

        $this->assertCount(2, $dependencies);
        $this->assertContains('Dependency1', $dependencies);
        $this->assertContains('Dependency2', $dependencies);
    }

    public function test_gets_module_dependents(): void
    {
        $this->createTestModule('BaseModule');
        $this->createTestModule('DependentModule1', [
            'dependencies' => ['BaseModule']
        ]);
        $this->createTestModule('DependentModule2', [
            'dependencies' => ['BaseModule']
        ]);

        $dependents = $this->moduleManager->getDependents('BaseModule');

        $this->assertCount(2, $dependents);
        $this->assertContains('DependentModule1', $dependents);
        $this->assertContains('DependentModule2', $dependents);
    }

    public function test_gets_module_info(): void
    {
        $this->createTestModule('TestModule', [
            'display_name' => 'Test Module',
            'version' => '2.0.0'
        ]);

        $moduleInfo = $this->moduleManager->getInfo('TestModule');

        $this->assertNotNull($moduleInfo);
        $this->assertEquals('TestModule', $moduleInfo->name);
        $this->assertEquals('Test Module', $moduleInfo->displayName);
        $this->assertEquals('2.0.0', $moduleInfo->version);
    }

    public function test_returns_null_for_nonexistent_module_info(): void
    {
        $moduleInfo = $this->moduleManager->getInfo('NonexistentModule');

        $this->assertNull($moduleInfo);
    }
}