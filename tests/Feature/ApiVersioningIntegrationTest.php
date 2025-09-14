<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Route;
use TaiCrm\LaravelModularDdd\ModularDddServiceProvider;

class ApiVersioningIntegrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ModularDddServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('modular-ddd.api.versions.supported', ['v1', 'v2']);
        $app['config']->set('modular-ddd.api.versions.default', 'v2');
        $app['config']->set('modular-ddd.api.versions.latest', 'v2');
        $app['config']->set('modular-ddd.api.versions.deprecated', ['v1']);
        $app['config']->set('modular-ddd.api.versions.sunset_dates', ['v1' => '2025-12-31']);
        $app['config']->set('modular-ddd.api.prefix', 'api');
    }

    protected function defineRoutes($router): void
    {
        // Test routes for different versions
        $router->get('/api/v1/test', function () {
            return response()->json(['version' => 'v1', 'message' => 'Hello from v1']);
        })->middleware(['api', 'api.version']);

        $router->get('/api/v2/test', function () {
            return response()->json(['version' => 'v2', 'message' => 'Hello from v2']);
        })->middleware(['api', 'api.version']);

        // Unversioned route (should default to v2)
        $router->get('/api/test', function () {
            $version = app('api.version') ?? 'unknown';
            return response()->json(['version' => $version, 'message' => 'Hello from default']);
        })->middleware(['api', 'api.version']);
    }

    public function test_version_discovery_endpoint(): void
    {
        $response = $this->getJson('/api/versions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'api' => ['name', 'description'],
                'versions' => [
                    'current',
                    'latest',
                    'supported',
                    'deprecated',
                ],
                'negotiation' => ['strategies', 'headers'],
                'capabilities',
                'links',
            ])
            ->assertJson([
                'versions' => [
                    'current' => 'v2',
                    'latest' => 'v2',
                    'deprecated' => ['v1'],
                ],
            ]);
    }

    public function test_url_version_negotiation(): void
    {
        $response = $this->getJson('/api/v1/test');

        $response->assertStatus(200)
            ->assertJson(['version' => 'v1'])
            ->assertHeader('X-API-Version', 'v1')
            ->assertHeader('X-API-Supported-Versions', 'v1, v2');
    }

    public function test_header_version_negotiation(): void
    {
        $response = $this->getJson('/api/test', [
            'Accept-Version' => 'v1'
        ]);

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v1')
            ->assertHeaderMissing('Warning'); // Should not have deprecation warning yet
    }

    public function test_query_parameter_version_negotiation(): void
    {
        $response = $this->getJson('/api/test?api_version=v2');

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v2');
    }

    public function test_default_version_fallback(): void
    {
        $response = $this->getJson('/api/test');

        $response->assertStatus(200)
            ->assertJson(['version' => 'v2'])
            ->assertHeader('X-API-Version', 'v2');
    }

    public function test_deprecation_warnings(): void
    {
        $response = $this->getJson('/api/v1/test');

        $response->assertStatus(200)
            ->assertHeader('Warning')
            ->assertHeader('Sunset', '2025-12-31')
            ->assertHeader('X-API-Latest-Version', 'v2');

        $warning = $response->headers->get('Warning');
        $this->assertStringContainsString('deprecated', $warning);
        $this->assertStringContainsString('v1', $warning);
    }

    public function test_unsupported_version_error(): void
    {
        $response = $this->getJson('/api/v99/test');

        $response->assertStatus(406)
            ->assertHeader('X-API-Supported-Versions', 'v1, v2')
            ->assertHeader('X-API-Latest-Version', 'v2')
            ->assertJson([
                'error' => 'Unsupported API Version',
                'requested_version' => 'v99',
                'supported_versions' => ['v1', 'v2'],
                'latest_version' => 'v2',
            ]);
    }

    public function test_version_priority_order(): void
    {
        // URL version should take priority over header
        $response = $this->getJson('/api/v1/test', [
            'Accept-Version' => 'v2'
        ]);

        $response->assertStatus(200)
            ->assertJson(['version' => 'v1'])
            ->assertHeader('X-API-Version', 'v1');
    }

    public function test_header_priority_over_query(): void
    {
        // Header should take priority over query parameter
        $response = $this->getJson('/api/test?api_version=v1', [
            'Accept-Version' => 'v2'
        ]);

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v2');
    }

    public function test_accept_header_with_version(): void
    {
        $response = $this->getJson('/api/test', [
            'Accept' => 'application/vnd.api+json;version=1'
        ]);

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v1');
    }

    public function test_non_api_routes_skip_versioning(): void
    {
        Route::get('/web/test', function () {
            return response()->json(['message' => 'web route']);
        });

        $response = $this->getJson('/web/test');

        $response->assertStatus(200)
            ->assertHeaderMissing('X-API-Version')
            ->assertJson(['message' => 'web route']);
    }

    public function test_version_context_available_in_controllers(): void
    {
        Route::get('/api/context-test', function () {
            return response()->json([
                'api_version' => app('api.version'),
                'request_version' => request()->attributes->get('api_version'),
            ]);
        })->middleware(['api', 'api.version']);

        $response = $this->getJson('/api/context-test', [
            'Accept-Version' => 'v1'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'api_version' => 'v1',
                'request_version' => 'v1',
            ]);
    }
}