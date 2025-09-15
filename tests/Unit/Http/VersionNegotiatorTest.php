<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use TaiCrm\LaravelModularDdd\Http\VersionNegotiator;
use TaiCrm\LaravelModularDdd\Exceptions\UnsupportedApiVersionException;

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

    public function test_negotiates_version_from_url(): void
    {
        $request = Request::create('/api/v2/users');

        $version = $this->negotiator->negotiate($request);

        $this->assertEquals('v2', $version);
    }

    public function test_negotiates_version_from_header(): void
    {
        $request = Request::create('/api/users');
        $request->headers->set('Accept-Version', 'v2');

        $version = $this->negotiator->negotiate($request);

        $this->assertEquals('v2', $version);
    }

    public function test_negotiates_version_from_query_parameter(): void
    {
        $request = Request::create('/api/users?api_version=v2');

        $version = $this->negotiator->negotiate($request);

        $this->assertEquals('v2', $version);
    }

    public function test_falls_back_to_default_version(): void
    {
        $request = Request::create('/api/users');

        $version = $this->negotiator->negotiate($request);

        $this->assertEquals('v2', $version);
    }

    public function test_throws_exception_for_unsupported_version(): void
    {
        $request = Request::create('/api/v99/users');

        $this->expectException(UnsupportedApiVersionException::class);
        $this->expectExceptionMessage("API version 'v99' is not supported");

        $this->negotiator->negotiate($request);
    }

    public function test_url_version_takes_priority_over_header(): void
    {
        $request = Request::create('/api/v1/users');
        $request->headers->set('Accept-Version', 'v2');

        $version = $this->negotiator->negotiate($request);

        $this->assertEquals('v1', $version);
    }

    public function test_header_version_takes_priority_over_query(): void
    {
        $request = Request::create('/api/users?api_version=v1');
        $request->headers->set('Accept-Version', 'v2');

        $version = $this->negotiator->negotiate($request);

        $this->assertEquals('v2', $version);
    }

    public function test_normalizes_version_format(): void
    {
        $request = Request::create('/api/users');
        $request->headers->set('Accept-Version', '2');

        $version = $this->negotiator->negotiate($request);

        $this->assertEquals('v2', $version);
    }

    public function test_validates_version_format(): void
    {
        $this->assertTrue($this->invokeMethod($this->negotiator, 'isValidVersionFormat', ['v1']));
        $this->assertTrue($this->invokeMethod($this->negotiator, 'isValidVersionFormat', ['v1.0']));
        $this->assertTrue($this->invokeMethod($this->negotiator, 'isValidVersionFormat', ['1']));
        $this->assertTrue($this->invokeMethod($this->negotiator, 'isValidVersionFormat', ['1.0']));
        $this->assertFalse($this->invokeMethod($this->negotiator, 'isValidVersionFormat', ['invalid']));
        $this->assertFalse($this->invokeMethod($this->negotiator, 'isValidVersionFormat', ['v1.0.0']));
    }

    public function test_gets_version_info(): void
    {
        $info = $this->negotiator->getVersionInfo('v1');

        $this->assertIsArray($info);
        $this->assertEquals('v1', $info['version']);
        $this->assertTrue($info['is_supported']);
        $this->assertFalse($info['is_latest']);
        $this->assertEquals(['v1', 'v2'], $info['supported_versions']);
        $this->assertEquals('v2', $info['latest_version']);
    }

    public function test_parses_accept_header_with_version(): void
    {
        $request = Request::create('/api/users');
        $request->headers->set('Accept', 'application/vnd.api+json;version=2');

        $version = $this->negotiator->getVersionFromHeaders($request);

        $this->assertEquals('v2', $version);
    }

    private function invokeMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}