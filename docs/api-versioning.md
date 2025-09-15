# API Versioning Guide

Laravel Modular DDD provides enterprise-grade API versioning capabilities with multi-strategy version negotiation, backward compatibility, and comprehensive lifecycle management.

## Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Version Negotiation Strategies](#version-negotiation-strategies)
- [Generating Versioned APIs](#generating-versioned-apis)
- [Configuration](#configuration)
- [Backward Compatibility](#backward-compatibility)
- [Version Discovery](#version-discovery)
- [Deprecation Management](#deprecation-management)
- [Testing Versioned APIs](#testing-versioned-apis)
- [Best Practices](#best-practices)

## Overview

The API versioning system provides:

- **Multi-Strategy Negotiation**: URL, headers, query parameters, and content negotiation
- **Backward Compatibility**: Automatic request/response transformation between versions
- **Version Discovery**: REST endpoints for API capabilities and version information
- **Deprecation Management**: Sunset dates and migration warnings
- **Module-Aware**: Per-module version configuration and management

## Quick Start

### 1. Enable API Versioning

Update your configuration in `config/modular-ddd.php`:

```php
'api' => [
    'versions' => [
        'supported' => ['v1', 'v2'],
        'default' => 'v2',
        'latest' => 'v2',
        'deprecated' => ['v1'],
        'sunset_dates' => [
            'v1' => '2025-12-31',
        ],
    ],
],
```

### 2. Generate Versioned API

```bash
# Generate API for all supported versions
php artisan module:make-api UserModule User --all-versions --auth --validation --swagger

# Generate API for specific version
php artisan module:make-api UserModule User --version=v2 --auth --validation --swagger
```

### 3. Test Version Negotiation

```bash
# URL-based versioning (highest priority)
curl http://localhost/api/v2/users

# Header-based versioning
curl -H "Accept-Version: v2" http://localhost/api/users

# Query parameter versioning
curl "http://localhost/api/users?api_version=v2"

# Content negotiation
curl -H "Accept: application/vnd.api+json;version=2" http://localhost/api/users
```

## Version Negotiation Strategies

### Priority Order

The system resolves versions in the following priority order:

1. **URL Path**: `/api/v2/users` (highest priority)
2. **Headers**: `Accept-Version`, `X-API-Version`, `Api-Version`
3. **Query Parameters**: `api_version`, `version`, `v`
4. **Content Negotiation**: `Accept: application/vnd.api+json;version=2`
5. **Default Fallback**: Configured default version

### URL-Based Versioning

```php
// modules/UserModule/Routes/api.php
Route::prefix('api/{version}')
    ->middleware(['api', 'api.version'])
    ->where('version', 'v[1-2]')
    ->group(function () {
        Route::apiResource('users', UserController::class);
    });
```

### Header-Based Versioning

The middleware automatically detects these headers:
- `Accept-Version: v2`
- `X-API-Version: v2`
- `Api-Version: v2`

### Query Parameter Versioning

Supported parameters:
- `?api_version=v2`
- `?version=v2`
- `?v=v2`

### Content Negotiation

```bash
curl -H "Accept: application/vnd.api+json;version=2" http://localhost/api/users
curl -H "Accept: application/json;version=1" http://localhost/api/users
```

## Generating Versioned APIs

### Command Options

```bash
# Generate for specific version
php artisan module:make-api UserModule User --version=v2

# Generate for all supported versions
php artisan module:make-api UserModule User --all-versions

# Generate with authentication and validation
php artisan module:make-api UserModule User --all-versions --auth --validation

# Generate with Swagger documentation
php artisan module:make-api UserModule User --all-versions --swagger
```

### Generated Structure

```
modules/UserModule/
├── Http/Controllers/Api/
│   ├── V1/UserController.php
│   └── V2/UserController.php
├── Routes/api.php (with versioned routes)
├── Docs/
│   ├── v1/UserApi.php
│   └── v2/UserApi.php
└── ...
```

### Version-Specific Controllers

```php
// modules/UserModule/Http/Controllers/Api/V1/UserController.php
namespace Modules\UserModule\Http\Controllers\Api\V1;

class UserController extends Controller
{
    public function index()
    {
        // v1 implementation
        return UserResource::collection(User::all());
    }
}

// modules/UserModule/Http/Controllers/Api/V2/UserController.php
namespace Modules\UserModule\Http\Controllers\Api\V2;

class UserController extends Controller
{
    public function index()
    {
        // v2 implementation with enhanced features
        return UserResource::collection(
            User::with('profile', 'preferences')->paginate(15)
        );
    }
}
```

## Configuration

### Global Configuration

```php
// config/modular-ddd.php
'api' => [
    'prefix' => 'api',
    'middleware' => ['api'],

    // Multi-version support
    'versions' => [
        'supported' => ['v1', 'v2', 'v3'],
        'default' => 'v3',
        'latest' => 'v3',
        'deprecated' => ['v1', 'v2'],
        'sunset_dates' => [
            'v1' => '2025-06-30',
            'v2' => '2026-12-31',
        ],
    ],

    // Version negotiation
    'negotiation' => [
        'strategy' => 'url,header,query,default',
        'headers' => [
            'Accept-Version',
            'X-API-Version',
            'Api-Version',
        ],
        'query_parameter' => ['api_version', 'version', 'v'],
        'accept_header_parsing' => true,
    ],

    // Backward compatibility
    'compatibility' => [
        'auto_transform' => true,
        'request_transformation' => true,
        'response_transformation' => true,
        'transform_cache_ttl' => 3600,
    ],

    // Documentation and discovery
    'documentation' => [
        'url' => '/api/docs',
        'discovery_endpoint' => '/api/versions',
        'include_deprecation_notices' => true,
    ],
],
```

### Module-Specific Configuration

```php
// config/modular-ddd.php
'modules' => [
    'UserModule' => [
        'api' => [
            'supported_versions' => ['v1', 'v2'],
            'default_version' => 'v2',
        ],
    ],
    'ProductModule' => [
        'api' => [
            'supported_versions' => ['v2', 'v3'],
            'default_version' => 'v3',
        ],
    ],
],
```

## Backward Compatibility

### Request Transformations

Create custom request transformers to upgrade old requests to new formats:

```php
use TaiCrm\LaravelModularDdd\Http\Compatibility\BaseRequestTransformer;

class UserV1ToV2RequestTransformer extends BaseRequestTransformer
{
    public function __construct()
    {
        parent::__construct('v1', 'v2', 100); // priority 100
    }

    protected function getDescription(): string
    {
        return 'Transform user requests from v1 to v2 format';
    }

    protected function getTransformationDescription(): array
    {
        return [
            'full_name -> first_name + last_name',
            'phone -> phone_number',
            'address -> address_line_1',
        ];
    }

    public function transform(Request $request, string $fromVersion, string $toVersion): Request
    {
        $data = $request->all();

        // Transform full_name to first_name and last_name
        if (isset($data['full_name'])) {
            $names = explode(' ', $data['full_name'], 2);
            $data['first_name'] = $names[0];
            $data['last_name'] = $names[1] ?? '';
            unset($data['full_name']);
        }

        // Transform phone to phone_number
        if (isset($data['phone'])) {
            $data['phone_number'] = $data['phone'];
            unset($data['phone']);
        }

        // Transform address to address_line_1
        if (isset($data['address'])) {
            $data['address_line_1'] = $data['address'];
            unset($data['address']);
        }

        return $this->cloneRequest($request)->replace($data);
    }
}
```

### Response Transformations

Create response transformers to downgrade new responses to old formats:

```php
use TaiCrm\LaravelModularDdd\Http\Compatibility\BaseResponseTransformer;

class UserV2ToV1ResponseTransformer extends BaseResponseTransformer
{
    public function __construct()
    {
        parent::__construct('v2', 'v1', 100);
    }

    protected function getDescription(): string
    {
        return 'Transform user responses from v2 to v1 format';
    }

    protected function getTransformationDescription(): array
    {
        return [
            'first_name + last_name -> full_name',
            'phone_number -> phone',
            'Remove: preferences, profile_image',
        ];
    }

    public function transform(array $data, string $fromVersion, string $toVersion): array
    {
        // Combine first_name and last_name into full_name
        if (isset($data['first_name']) || isset($data['last_name'])) {
            $data['full_name'] = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
            unset($data['first_name'], $data['last_name']);
        }

        // Transform phone_number to phone
        if (isset($data['phone_number'])) {
            $data['phone'] = $data['phone_number'];
            unset($data['phone_number']);
        }

        // Remove v2-specific fields
        unset($data['preferences'], $data['profile_image']);

        // Handle nested data (collections)
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $index => $item) {
                if (is_array($item)) {
                    $data['data'][$index] = $this->transformSingleItem($item);
                }
            }
        }

        return $data;
    }

    private function transformSingleItem(array $item): array
    {
        // Apply same transformations to individual items
        if (isset($item['first_name']) || isset($item['last_name'])) {
            $item['full_name'] = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''));
            unset($item['first_name'], $item['last_name']);
        }

        if (isset($item['phone_number'])) {
            $item['phone'] = $item['phone_number'];
            unset($item['phone_number']);
        }

        unset($item['preferences'], $item['profile_image']);

        return $item;
    }
}
```

### Registering Transformers

```php
// In a service provider (e.g., UserModuleServiceProvider)
public function boot()
{
    $registry = app(TransformationRegistry::class);

    // Register request transformer
    $registry->registerRequestTransformer(
        'v1', 'v2',
        new UserV1ToV2RequestTransformer(),
        'UserModule'
    );

    // Register response transformer
    $registry->registerResponseTransformer(
        'v2', 'v1',
        new UserV2ToV1ResponseTransformer(),
        'UserModule'
    );
}
```

## Version Discovery

### Global Version Information

```bash
# Get all API versions and capabilities
curl -H "Accept: application/json" http://localhost/api/versions
```

Response:
```json
{
  "api": {
    "name": "Your Application API",
    "description": "Modular Domain-Driven Design API"
  },
  "versions": {
    "current": "v2",
    "latest": "v2",
    "supported": [
      {
        "version": "v1",
        "status": "deprecated",
        "sunset_date": "2025-12-31",
        "base_url": "http://localhost/api/v1"
      },
      {
        "version": "v2",
        "status": "active",
        "base_url": "http://localhost/api/v2"
      }
    ]
  },
  "negotiation": {
    "strategies": ["url", "header", "query", "default"],
    "headers": ["Accept-Version", "X-API-Version"]
  },
  "capabilities": {
    "version_negotiation": true,
    "backward_compatibility": true,
    "deprecation_warnings": true
  }
}
```

### Module-Specific Version Information

```bash
# Get version info for specific module
curl -H "Accept: application/json" http://localhost/api/modules/UserModule/versions
```

Response:
```json
{
  "module": {
    "name": "UserModule",
    "display_name": "User Management",
    "version": "1.2.0",
    "status": "enabled"
  },
  "api_versions": [
    {
      "version": "v1",
      "status": "deprecated",
      "base_url": "http://localhost/api/v1/users"
    },
    {
      "version": "v2",
      "status": "active",
      "base_url": "http://localhost/api/v2/users"
    }
  ],
  "endpoints": {
    "count": 5,
    "discovery_url": "http://localhost/api/docs/modules/UserModule/endpoints"
  }
}
```

## Deprecation Management

### Response Headers

When using deprecated versions, the API automatically includes deprecation warnings:

```http
HTTP/1.1 200 OK
X-API-Version: v1
X-API-Supported-Versions: v1, v2
X-API-Latest-Version: v2
Warning: 299 - "This API version (v1) is deprecated. It will be sunset on 2025-12-31."
Sunset: 2025-12-31
```

### Programmatic Deprecation Checking

```php
use TaiCrm\LaravelModularDdd\Http\VersionNegotiator;

$negotiator = app(VersionNegotiator::class);
$versionInfo = $negotiator->getVersionInfo('v1');

if ($versionInfo['is_deprecated']) {
    // Handle deprecated version
    Log::warning('Deprecated API version used', [
        'version' => $versionInfo['version'],
        'sunset_date' => $versionInfo['sunset_date'],
        'user_agent' => request()->userAgent(),
        'ip' => request()->ip(),
    ]);
}
```

## Testing Versioned APIs

### Feature Tests

```php
use Tests\TestCase;

class UserApiVersioningTest extends TestCase
{
    /** @test */
    public function it_negotiates_version_from_url()
    {
        $response = $this->getJson('/api/v2/users');

        $response->assertStatus(200)
                ->assertHeader('X-API-Version', 'v2');
    }

    /** @test */
    public function it_negotiates_version_from_header()
    {
        $response = $this->getJson('/api/users', [
            'Accept-Version' => 'v1'
        ]);

        $response->assertStatus(200)
                ->assertHeader('X-API-Version', 'v1')
                ->assertHeader('Warning'); // Deprecation warning
    }

    /** @test */
    public function it_returns_error_for_unsupported_version()
    {
        $response = $this->getJson('/api/v99/users');

        $response->assertStatus(406)
                ->assertJson([
                    'error' => 'Unsupported API Version',
                    'requested_version' => 'v99',
                    'supported_versions' => ['v1', 'v2']
                ]);
    }

    /** @test */
    public function it_transforms_v1_request_to_v2_format()
    {
        $v1Data = [
            'full_name' => 'John Doe',
            'phone' => '+1234567890'
        ];

        $response = $this->postJson('/api/v2/users', $v1Data, [
            'Accept-Version' => 'v1'
        ]);

        $response->assertStatus(201);

        // Verify the request was transformed internally
        $this->assertDatabaseHas('users', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone_number' => '+1234567890'
        ]);
    }
}
```

### Unit Tests for Transformers

```php
use Tests\TestCase;
use Illuminate\Http\Request;

class UserV1ToV2RequestTransformerTest extends TestCase
{
    /** @test */
    public function it_transforms_full_name_to_first_and_last_name()
    {
        $transformer = new UserV1ToV2RequestTransformer();
        $request = Request::create('/api/users', 'POST', [
            'full_name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $transformedRequest = $transformer->transform($request, 'v1', 'v2');

        $this->assertEquals('John', $transformedRequest->input('first_name'));
        $this->assertEquals('Doe', $transformedRequest->input('last_name'));
        $this->assertNull($transformedRequest->input('full_name'));
        $this->assertEquals('john@example.com', $transformedRequest->input('email'));
    }
}
```

## Best Practices

### 1. Version Planning

- **Semantic Versioning**: Use semantic version numbers (v1, v2, v3)
- **Forward Compatibility**: Design APIs to be extensible
- **Breaking Changes**: Only introduce breaking changes in major versions
- **Documentation**: Maintain comprehensive documentation for each version

### 2. Deprecation Strategy

- **Gradual Deprecation**: Give users time to migrate (6-12 months)
- **Clear Communication**: Document migration paths and breaking changes
- **Monitoring**: Track usage of deprecated versions
- **Support**: Provide migration assistance and tooling

### 3. Backward Compatibility

- **Additive Changes**: Add new fields without removing old ones
- **Transformers**: Use transformers for complex compatibility requirements
- **Caching**: Cache transformation results for performance
- **Testing**: Thoroughly test all version combinations

### 4. Performance Considerations

- **Route Caching**: Cache version-aware routes properly
- **Transformation Caching**: Cache transformation results
- **Monitoring**: Monitor performance impact of versioning
- **Optimization**: Optimize for the most commonly used versions

### 5. Security

- **Input Validation**: Validate inputs for all versions
- **Authorization**: Ensure authorization works across versions
- **Audit Logging**: Log version usage for security monitoring
- **Rate Limiting**: Apply rate limiting consistently across versions

### 6. Monitoring and Analytics

- **Version Usage**: Track which versions are being used
- **Performance Metrics**: Monitor performance per version
- **Error Rates**: Track errors by version
- **User Behavior**: Analyze how users interact with different versions

---

This guide provides comprehensive coverage of the API versioning system. For more advanced scenarios and customization options, refer to the source code documentation in the `src/Http/` directory.