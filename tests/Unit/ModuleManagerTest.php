<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Tests\Unit;

use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\Exceptions\DependencyException;
use TaiCrm\LaravelModularDdd\Exceptions\ModuleNotFoundException;
use TaiCrm\LaravelModularDdd\Tests\TestCase;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleState;

class ModuleManagerTest extends TestCase
{
    private ModuleManagerInterface $moduleManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->moduleManager = $this->app->make(ModuleManagerInterface::class);
    }

    public function testListReturnsEmptyCollectionWhenNoModules(): void
    {
        $modules = $this->moduleManager->list();

        $this->assertEmpty($modules);
    }

    public function testListReturnsDiscoveredModules(): void
    {
        $this->createTestModule('TestModule');
        $this->createTestModule('AnotherModule');

        $modules = $this->moduleManager->list();

        $this->assertCount(2, $modules);
        $this->assertTrue($modules->has('TestModule'));
        $this->assertTrue($modules->has('AnotherModule'));
    }

    public function testCanInstallModule(): void
    {
        $this->createTestModule('TestModule');

        $result = $this->moduleManager->install('TestModule');

        $this->assertTrue($result);
        $this->assertTrue($this->moduleManager->isInstalled('TestModule'));
        $this->assertSame(ModuleState::Installed, $this->moduleManager->getState('TestModule'));
    }

    public function testCannotInstallNonexistentModule(): void
    {
        $this->expectException(ModuleNotFoundException::class);

        $this->moduleManager->install('NonexistentModule');
    }

    public function testCanEnableInstalledModule(): void
    {
        $this->createTestModule('TestModule');
        $this->moduleManager->install('TestModule');

        $result = $this->moduleManager->enable('TestModule');

        $this->assertTrue($result);
        $this->assertTrue($this->moduleManager->isEnabled('TestModule'));
        $this->assertSame(ModuleState::Enabled, $this->moduleManager->getState('TestModule'));
    }

    public function testCanDisableEnabledModule(): void
    {
        $this->createTestModule('TestModule');
        $this->moduleManager->install('TestModule');
        $this->moduleManager->enable('TestModule');

        $result = $this->moduleManager->disable('TestModule');

        $this->assertTrue($result);
        $this->assertFalse($this->moduleManager->isEnabled('TestModule'));
        $this->assertSame(ModuleState::Disabled, $this->moduleManager->getState('TestModule'));
    }

    public function testCanRemoveInstalledModule(): void
    {
        $this->createTestModule('TestModule');
        $this->moduleManager->install('TestModule');

        $result = $this->moduleManager->remove('TestModule');

        $this->assertTrue($result);
        $this->assertFalse($this->moduleManager->isInstalled('TestModule'));
        $this->assertSame(ModuleState::NotInstalled, $this->moduleManager->getState('TestModule'));
    }

    public function testValidatesDependenciesBeforeInstallation(): void
    {
        $this->createTestModule('DependentModule', [
            'dependencies' => ['MissingModule'],
        ]);

        $this->expectException(DependencyException::class);
        $this->expectExceptionMessage('missing dependencies');

        $this->moduleManager->install('DependentModule');
    }

    public function testInstallsDependenciesAutomatically(): void
    {
        $this->createTestModule('DependencyModule');
        $this->createTestModule('MainModule', [
            'dependencies' => ['DependencyModule'],
        ]);

        $result = $this->moduleManager->install('MainModule');

        $this->assertTrue($result);
        $this->assertTrue($this->moduleManager->isInstalled('DependencyModule'));
        $this->assertTrue($this->moduleManager->isInstalled('MainModule'));
    }

    public function testGetsModuleDependencies(): void
    {
        $this->createTestModule('TestModule', [
            'dependencies' => ['Dependency1', 'Dependency2'],
        ]);

        $dependencies = $this->moduleManager->getDependencies('TestModule');

        $this->assertCount(2, $dependencies);
        $this->assertContains('Dependency1', $dependencies);
        $this->assertContains('Dependency2', $dependencies);
    }

    public function testGetsModuleDependents(): void
    {
        $this->createTestModule('BaseModule');
        $this->createTestModule('DependentModule1', [
            'dependencies' => ['BaseModule'],
        ]);
        $this->createTestModule('DependentModule2', [
            'dependencies' => ['BaseModule'],
        ]);

        $dependents = $this->moduleManager->getDependents('BaseModule');

        $this->assertCount(2, $dependents);
        $this->assertContains('DependentModule1', $dependents);
        $this->assertContains('DependentModule2', $dependents);
    }

    public function testGetsModuleInfo(): void
    {
        $this->createTestModule('TestModule', [
            'display_name' => 'Test Module',
            'version' => '2.0.0',
        ]);

        $moduleInfo = $this->moduleManager->getInfo('TestModule');

        $this->assertNotNull($moduleInfo);
        $this->assertSame('TestModule', $moduleInfo->name);
        $this->assertSame('Test Module', $moduleInfo->displayName);
        $this->assertSame('2.0.0', $moduleInfo->version);
    }

    public function testReturnsNullForNonexistentModuleInfo(): void
    {
        $moduleInfo = $this->moduleManager->getInfo('NonexistentModule');

        $this->assertNull($moduleInfo);
    }
}
