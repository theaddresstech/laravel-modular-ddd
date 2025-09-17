<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\TestCase;
use TaiCrm\LaravelModularDdd\Exceptions\UnsupportedApiVersionException;
use TaiCrm\LaravelModularDdd\Http\Middleware\ApiVersionMiddleware;
use TaiCrm\LaravelModularDdd\Http\VersionNegotiator;

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

    public function testSkipsNonApiRoutes(): void
    {
        $request = Request::create('/web/users');
        $nextCalled = false;

        $next = static function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return new Response('OK');
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertSame('OK', $response->getContent());
    }

    public function testProcessesApiRoutes(): void
    {
        $request = Request::create('/api/users');

        $this->versionNegotiator
            ->expects($this->once())
            ->method('negotiate')
            ->with($request, null)
            ->willReturn('v2');

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.supported', ['v1'])
            ->andReturn(['v1', 'v2']);

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.deprecated', [])
            ->andReturn([]);

        $nextCalled = false;
        $next = static function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return new Response('API Response');
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertSame('v2', $request->attributes->get('api_version'));
        $this->assertSame('v2', $response->headers->get('X-API-Version'));
    }

    public function testSetsVersionContext(): void
    {
        $request = Request::create('/api/users');

        $this->versionNegotiator
            ->expects($this->once())
            ->method('negotiate')
            ->with($request, null)
            ->willReturn('v1');

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.supported', ['v1'])
            ->andReturn(['v1', 'v2']);

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.deprecated', [])
            ->andReturn([]);

        $next = function ($req) {
            // Verify context is set during request processing
            $this->assertSame('v1', app('api.version'));

            return new Response('OK');
        };

        $this->middleware->handle($request, $next);
    }

    public function testAddsVersionHeadersToResponse(): void
    {
        $request = Request::create('/api/users');

        $this->versionNegotiator
            ->method('negotiate')
            ->willReturn('v2');

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.supported', ['v1'])
            ->andReturn(['v1', 'v2']);

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.deprecated', [])
            ->andReturn([]);

        $next = static fn () => new Response('API Response');

        $response = $this->middleware->handle($request, $next);

        $this->assertSame('v2', $response->headers->get('X-API-Version'));
        $this->assertSame('v1, v2', $response->headers->get('X-API-Supported-Versions'));
    }

    public function testAddsDeprecationWarnings(): void
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

        $next = static fn () => new Response('API Response');

        $response = $this->middleware->handle($request, $next);

        $warning = $response->headers->get('Warning');
        $this->assertStringContains('deprecated', $warning);
        $this->assertStringContains('v1', $warning);
        $this->assertSame('2025-12-31', $response->headers->get('Sunset'));
        $this->assertSame('v2', $response->headers->get('X-API-Latest-Version'));
    }

    public function testHandlesUnsupportedVersionException(): void
    {
        $request = Request::create('/api/v99/users');

        $this->versionNegotiator
            ->method('negotiate')
            ->willThrowException(new UnsupportedApiVersionException(
                "API version 'v99' is not supported",
                'v99',
                ['v1', 'v2'],
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

        $next = static fn () => new Response('Should not be called');

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(406, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Unsupported API Version', $data['error']);
        $this->assertSame('v99', $data['requested_version']);
        $this->assertSame(['v1', 'v2'], $data['supported_versions']);
        $this->assertSame('v2', $data['latest_version']);
    }

    public function testHandlesModuleSpecificVersioning(): void
    {
        $request = Request::create('/api/users');

        $this->versionNegotiator
            ->expects($this->once())
            ->method('negotiate')
            ->with($request, 'TestModule')
            ->willReturn('v1');

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.supported', ['v1'])
            ->andReturn(['v1', 'v2']);

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.deprecated', [])
            ->andReturn([]);

        $next = static fn () => new Response('OK');

        $this->middleware->handle($request, $next, 'TestModule');

        $this->assertSame('TestModule', $request->attributes->get('api_module'));
    }

    private function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack);
        $this->assertStringContainsString($needle, $haystack, "String '{$haystack}' does not contain '{$needle}'");
    }
}
