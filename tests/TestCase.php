<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;
use TaiCrm\LaravelModularDdd\ModularDddServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTestEnvironment();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestFiles();
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ModularDddServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('modular-ddd.modules_path', $this->getTestModulesPath());
        $app['config']->set('modular-ddd.registry_storage', $this->getTestStoragePath());
        $app['config']->set('modular-ddd.auto_discovery', false);
        $app['config']->set('modular-ddd.cache.enabled', false);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUpTestEnvironment(): void
    {
        $this->createTestDirectories();
        $this->cleanupTestFiles();
    }

    protected function getTestModulesPath(): string
    {
        return __DIR__ . '/fixtures/modules';
    }

    protected function getTestStoragePath(): string
    {
        return __DIR__ . '/fixtures/storage';
    }

    protected function createTestDirectories(): void
    {
        $directories = [
            $this->getTestModulesPath(),
            $this->getTestStoragePath(),
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0o755, true);
            }
        }
    }

    protected function cleanupTestFiles(): void
    {
        $paths = [
            $this->getTestModulesPath(),
            $this->getTestStoragePath(),
        ];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $this->removeDirectory($path);
            }
        }

        $this->createTestDirectories();
    }

    protected function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = array_diff(scandir($path), ['.', '..']);

        foreach ($files as $file) {
            $filePath = $path . DIRECTORY_SEPARATOR . $file;

            if (is_dir($filePath)) {
                $this->removeDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }

        rmdir($path);
    }

    protected function createTestModule(string $name, array $manifest = []): string
    {
        $modulePath = $this->getTestModulesPath() . '/' . $name;

        // Create directory structure
        $directories = [
            'Domain',
            'Application',
            'Infrastructure',
            'Presentation',
        ];

        foreach ($directories as $directory) {
            mkdir($modulePath . '/' . $directory, 0o755, true);
        }

        // Create manifest
        $defaultManifest = [
            'name' => $name,
            'display_name' => $name,
            'description' => "Test module {$name}",
            'version' => '1.0.0',
            'author' => 'Test',
            'dependencies' => [],
            'optional_dependencies' => [],
            'conflicts' => [],
            'provides' => [],
        ];

        $manifest = array_merge($defaultManifest, $manifest);

        file_put_contents(
            $modulePath . '/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT),
        );

        return $modulePath;
    }

    protected function assertModuleExists(string $name): void
    {
        $modulePath = $this->getTestModulesPath() . '/' . $name;
        $this->assertDirectoryExists($modulePath);
        $this->assertFileExists($modulePath . '/manifest.json');
    }

    protected function assertModuleNotExists(string $name): void
    {
        $modulePath = $this->getTestModulesPath() . '/' . $name;
        $this->assertDirectoryDoesNotExist($modulePath);
    }
}
