<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Tests\Unit\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TaiCrm\LaravelModularDdd\Exceptions\UnsupportedApiVersionException;
use TaiCrm\LaravelModularDdd\Http\VersionNegotiator;

class VersionNegotiatorTest extends TestCase
{
    private VersionNegotiator $negotiator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->negotiator = new VersionNegotiator();

        // Mock config values
        Config::shouldReceive('get')
            ->with('modular-ddd.api.prefix', 'api')
            ->andReturn('api');

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.supported', ['v1'])
            ->andReturn(['v1', 'v2']);

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.default')
            ->andReturn('v2');

        Config::shouldReceive('get')
            ->with('modular-ddd.api.version', 'v1')
            ->andReturn('v1');

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.sunset_dates', [])
            ->andReturn([]);

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.deprecated', [])
            ->andReturn([]);

        Config::shouldReceive('get')
            ->with('modular-ddd.api.versions.latest', 'v1')
            ->andReturn('v2');
    }

    public function testNegotiatesVersionFromUrl(): void
    {
        $request = Request::create('/api/v2/users');

        $version = $this->negotiator->negotiate($request);

        $this->assertSame('v2', $version);
    }

    public function testNegotiatesVersionFromHeader(): void
    {
        $request = Request::create('/api/users');
        $request->headers->set('Accept-Version', 'v2');

        $version = $this->negotiator->negotiate($request);

        $this->assertSame('v2', $version);
    }

    public function testNegotiatesVersionFromQueryParameter(): void
    {
        $request = Request::create('/api/users?api_version=v2');

        $version = $this->negotiator->negotiate($request);

        $this->assertSame('v2', $version);
    }

    public function testFallsBackToDefaultVersion(): void
    {
        $request = Request::create('/api/users');

        $version = $this->negotiator->negotiate($request);

        $this->assertSame('v2', $version);
    }

    public function testThrowsExceptionForUnsupportedVersion(): void
    {
        $request = Request::create('/api/v99/users');

        $this->expectException(UnsupportedApiVersionException::class);
        $this->expectExceptionMessage("API version 'v99' is not supported");

        $this->negotiator->negotiate($request);
    }

    public function testUrlVersionTakesPriorityOverHeader(): void
    {
        $request = Request::create('/api/v1/users');
        $request->headers->set('Accept-Version', 'v2');

        $version = $this->negotiator->negotiate($request);

        $this->assertSame('v1', $version);
    }

    public function testHeaderVersionTakesPriorityOverQuery(): void
    {
        $request = Request::create('/api/users?api_version=v1');
        $request->headers->set('Accept-Version', 'v2');

        $version = $this->negotiator->negotiate($request);

        $this->assertSame('v2', $version);
    }

    public function testNormalizesVersionFormat(): void
    {
        $request = Request::create('/api/users');
        $request->headers->set('Accept-Version', '2');

        $version = $this->negotiator->negotiate($request);

        $this->assertSame('v2', $version);
    }

    public function testValidatesVersionFormat(): void
    {
        $this->assertTrue($this->invokeMethod($this->negotiator, 'isValidVersionFormat', ['v1']));
        $this->assertTrue($this->invokeMethod($this->negotiator, 'isValidVersionFormat', ['v1.0']));
        $this->assertTrue($this->invokeMethod($this->negotiator, 'isValidVersionFormat', ['1']));
        $this->assertTrue($this->invokeMethod($this->negotiator, 'isValidVersionFormat', ['1.0']));
        $this->assertFalse($this->invokeMethod($this->negotiator, 'isValidVersionFormat', ['invalid']));
        $this->assertFalse($this->invokeMethod($this->negotiator, 'isValidVersionFormat', ['v1.0.0']));
    }

    public function testGetsVersionInfo(): void
    {
        $info = $this->negotiator->getVersionInfo('v1');

        $this->assertIsArray($info);
        $this->assertSame('v1', $info['version']);
        $this->assertTrue($info['is_supported']);
        $this->assertFalse($info['is_latest']);
        $this->assertSame(['v1', 'v2'], $info['supported_versions']);
        $this->assertSame('v2', $info['latest_version']);
    }

    public function testParsesAcceptHeaderWithVersion(): void
    {
        $request = Request::create('/api/users');
        $request->headers->set('Accept', 'application/vnd.api+json;version=2');

        $version = $this->negotiator->getVersionFromHeaders($request);

        $this->assertSame('v2', $version);
    }

    private function invokeMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass($object::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
