<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Tests\Unit\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use TaiCrm\LaravelModularDdd\Http\Middleware\ApiVersionMiddleware;
use TaiCrm\LaravelModularDdd\Http\VersionNegotiator;
use TaiCrm\LaravelModularDdd\Exceptions\UnsupportedApiVersionException;

class ApiVersionMiddlewareTest extends TestCase
{
    private ApiVersionMiddleware $middleware;
    private VersionNegotiator $versionNegotiator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->versionNegotiator = $this->createMock(VersionNegotiator::class);
        $this->middleware = new ApiVersionMiddleware($this->versionNegotiator);

        // Mock config
        Config::shouldReceive('get')
            ->with('modular-ddd.api.prefix', 'api')
            ->andReturn('api');
    }

    public function test_skips_non_api_routes(): void
    {
        $request = Request::create('/web/users');
        $nextCalled = false;

        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return new Response('OK');
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_processes_api_routes(): void
    {
        $request = Request::create('/api/users');

        $this->versionNegotiator
            ->expects($this->once())
            ->method('negotiate')
            ->with($request, null)
            ->willReturn('v2');

        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return new Response('API Response');
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('v2', $request->attributes->get('api_version'));
        $this->assertEquals('v2', $response->headers->get('X-API-Version'));
    }

    public function test_sets_version_context(): void
    {
        $request = Request::create('/api/users');

        $this->versionNegotiator
            ->expects($this->once())
            ->method('negotiate')
            ->with($request, null)
            ->willReturn('v1');

        $next = function ($req) {
            // Verify context is set during request processing
            $this->assertEquals('v1', app('api.version'));
            return new Response('OK');
        };

        $this->middleware->handle($request, $next);
    }

    public function test_adds_version_headers_to_response(): void
    {
        $request = Request::create('/api/users');

        $this->versionNegotiator
            ->method('negotiate')
            ->willReturn('v2');

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.supported', ['v1'])
            ->andReturn(['v1', 'v2']);

        $next = function () {
            return new Response('API Response');
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('v2', $response->headers->get('X-API-Version'));
        $this->assertEquals('v1, v2', $response->headers->get('X-API-Supported-Versions'));
    }

    public function test_adds_deprecation_warnings(): void
    {
        $request = Request::create('/api/v1/users');

        $this->versionNegotiator
            ->method('negotiate')
            ->willReturn('v1');

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.deprecated', [])
            ->andReturn(['v1']);

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.sunset_dates', [])
            ->andReturn(['v1' => '2025-12-31']);

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.latest', 'v1')
            ->andReturn('v2');

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.supported', ['v1'])
            ->andReturn(['v1', 'v2']);

        $next = function () {
            return new Response('API Response');
        };

        $response = $this->middleware->handle($request, $next);

        $warning = $response->headers->get('Warning');
        $this->assertStringContains('deprecated', $warning);
        $this->assertStringContains('v1', $warning);
        $this->assertEquals('2025-12-31', $response->headers->get('Sunset'));
        $this->assertEquals('v2', $response->headers->get('X-API-Latest-Version'));
    }

    public function test_handles_unsupported_version_exception(): void
    {
        $request = Request::create('/api/v99/users');

        $this->versionNegotiator
            ->method('negotiate')
            ->willThrowException(new UnsupportedApiVersionException(
                "API version 'v99' is not supported",
                'v99',
                ['v1', 'v2']
            ));

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.supported', ['v1'])
            ->andReturn(['v1', 'v2']);

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.latest', 'v1')
            ->andReturn('v2');

        Config::shouldReceive('get')
            ->with('modular-ddd.api.documentation_url')
            ->andReturn('/api/docs');

        $next = function () {
            return new Response('Should not be called');
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(406, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Unsupported API Version', $data['error']);
        $this->assertEquals('v99', $data['requested_version']);
        $this->assertEquals(['v1', 'v2'], $data['supported_versions']);
        $this->assertEquals('v2', $data['latest_version']);
    }

    public function test_handles_module_specific_versioning(): void
    {
        $request = Request::create('/api/users');

        $this->versionNegotiator
            ->expects($this->once())
            ->method('negotiate')
            ->with($request, 'TestModule')
            ->willReturn('v1');

        $next = function () {
            return new Response('OK');
        };

        $response = $this->middleware->handle($request, $next, 'TestModule');

        $this->assertEquals('TestModule', $request->attributes->get('api_module'));
    }

    private function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack);
        $this->assertTrue(str_contains($haystack, $needle), "String '{$haystack}' does not contain '{$needle}'");
    }
}