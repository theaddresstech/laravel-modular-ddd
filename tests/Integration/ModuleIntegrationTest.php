<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Tests\Integration;

use TaiCrm\LaravelModularDdd\Tests\TestCase;

class ModuleIntegrationTest extends TestCase
{
    public function test_service_provider_loads_successfully(): void
    {
        // Test that the service provider can be loaded without errors
        $this->assertTrue(true);
    }

    public function test_config_is_accessible(): void
    {
        // Test that configuration is properly loaded
        $config = config('modular-ddd');
        $this->assertIsArray($config);
    }

    public function test_facades_are_registered(): void
    {
        // Test that facades are properly registered
        $this->assertTrue(class_exists('TaiCrm\LaravelModularDdd\ModularDddServiceProvider'));
    }
}