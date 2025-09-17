<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Tests\Unit;

use InvalidArgumentException;
use TaiCrm\LaravelModularDdd\ModuleManager\DependencyResolver;
use TaiCrm\LaravelModularDdd\Tests\TestCase;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleState;

class DependencyResolverTest extends TestCase
{
    private DependencyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new DependencyResolver();
    }

    public function testValidatesDependenciesWithAllAvailable(): void
    {
        $module = $this->createModuleInfo('TestModule', ['Dependency1', 'Dependency2']);
        $available = collect([
            $this->createModuleInfo('Dependency1'),
            $this->createModuleInfo('Dependency2'),
        ]);

        $missing = $this->resolver->validateDependencies($module, $available);

        $this->assertEmpty($missing);
    }

    public function testValidatesDependenciesWithMissing(): void
    {
        $module = $this->createModuleInfo('TestModule', ['Dependency1', 'MissingDependency']);
        $available = collect([
            $this->createModuleInfo('Dependency1'),
        ]);

        $missing = $this->resolver->validateDependencies($module, $available);

        $this->assertContains('MissingDependency', $missing);
    }

    public function testDetectsCircularDependency(): void
    {
        $moduleA = $this->createModuleInfo('ModuleA', ['ModuleB']);
        $moduleB = $this->createModuleInfo('ModuleB', ['ModuleA']);
        $modules = collect([$moduleA, $moduleB]);

        $hasCircular = $this->resolver->hasCircularDependency($moduleA, $modules);

        $this->assertTrue($hasCircular);
    }

    public function testDoesNotDetectCircularDependencyForValidChain(): void
    {
        $moduleA = $this->createModuleInfo('ModuleA', ['ModuleB']);
        $moduleB = $this->createModuleInfo('ModuleB', ['ModuleC']);
        $moduleC = $this->createModuleInfo('ModuleC', []);
        $modules = collect([$moduleA, $moduleB, $moduleC]);

        $hasCircular = $this->resolver->hasCircularDependency($moduleA, $modules);

        $this->assertFalse($hasCircular);
    }

    public function testGetsInstallOrderWithDependencies(): void
    {
        $moduleA = $this->createModuleInfo('ModuleA', ['ModuleB']);
        $moduleB = $this->createModuleInfo('ModuleB', ['ModuleC']);
        $moduleC = $this->createModuleInfo('ModuleC', []);
        $modules = collect([$moduleA, $moduleB, $moduleC]);

        $order = $this->resolver->getInstallOrder($modules);

        $names = $order->pluck('name')->toArray();
        $this->assertSame(['ModuleC', 'ModuleB', 'ModuleA'], $names);
    }

    public function testGetsInstallOrderWithNoDependencies(): void
    {
        $moduleA = $this->createModuleInfo('ModuleA', []);
        $moduleB = $this->createModuleInfo('ModuleB', []);
        $modules = collect([$moduleA, $moduleB]);

        $order = $this->resolver->getInstallOrder($modules);

        $this->assertCount(2, $order);
        $this->assertContains('ModuleA', $order->pluck('name'));
        $this->assertContains('ModuleB', $order->pluck('name'));
    }

    public function testCanRemoveModuleWithoutDependents(): void
    {
        $moduleA = $this->createModuleInfo('ModuleA', []);
        $moduleB = $this->createModuleInfo('ModuleB', []);
        $modules = collect([$moduleA, $moduleB]);

        $canRemove = $this->resolver->canRemove('ModuleA', $modules);

        $this->assertTrue($canRemove);
    }

    public function testCannotRemoveModuleWithEnabledDependents(): void
    {
        $moduleA = $this->createModuleInfo('ModuleA', []);
        $moduleB = $this->createModuleInfo('ModuleB', ['ModuleA'], ModuleState::Enabled);
        $modules = collect([$moduleA, $moduleB]);

        $canRemove = $this->resolver->canRemove('ModuleA', $modules);

        $this->assertFalse($canRemove);
    }

    public function testGetsDependents(): void
    {
        $moduleA = $this->createModuleInfo('ModuleA', []);
        $moduleB = $this->createModuleInfo('ModuleB', ['ModuleA']);
        $moduleC = $this->createModuleInfo('ModuleC', ['ModuleA']);
        $modules = collect([$moduleA, $moduleB, $moduleC]);

        $dependents = $this->resolver->getDependents('ModuleA', $modules);

        $this->assertCount(2, $dependents);
        $this->assertContains('ModuleB', $dependents);
        $this->assertContains('ModuleC', $dependents);
    }

    public function testThrowsExceptionForCircularDependencyInTopologicalSort(): void
    {
        $moduleA = $this->createModuleInfo('ModuleA', ['ModuleB']);
        $moduleB = $this->createModuleInfo('ModuleB', ['ModuleA']);
        $modules = collect([$moduleA, $moduleB]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $this->resolver->getInstallOrder($modules);
    }

    private function createModuleInfo(
        string $name,
        array $dependencies = [],
        ModuleState $state = ModuleState::NotInstalled,
    ): ModuleInfo {
        return ModuleInfo::fromArray([
            'name' => $name,
            'display_name' => $name,
            'description' => "Test module {$name}",
            'version' => '1.0.0',
            'author' => 'Test',
            'dependencies' => $dependencies,
            'optional_dependencies' => [],
            'conflicts' => [],
            'provides' => [],
            'path' => "/test/modules/{$name}",
            'state' => $state,
        ]);
    }
}
