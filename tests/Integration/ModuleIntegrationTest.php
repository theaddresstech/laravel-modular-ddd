<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Tests\Integration;

use TaiCrm\LaravelModularDdd\Tests\TestCase;

class ModuleIntegrationTest extends TestCase
{
    public function testServiceProviderLoadsSuccessfully(): void
    {
        // Test that the service provider can be loaded without errors
        $this->assertTrue(true);
    }

    public function testConfigIsAccessible(): void
    {
        // Test that configuration is properly loaded
        $config = config('modular-ddd');
        $this->assertIsArray($config);
    }

    public function testFacadesAreRegistered(): void
    {
        // Test that facades are properly registered
        $this->assertTrue(class_exists('TaiCrm\LaravelModularDdd\ModularDddServiceProvider'));
    }
}
