# Laravel Modular DDD - Complete Developer Guide

[![Latest Version](https://img.shields.io/packagist/v/mghrby/laravel-modular-ddd.svg)](https://packagist.org/packages/mghrby/laravel-modular-ddd)
[![License](https://img.shields.io/packagist/l/mghrby/laravel-modular-ddd.svg)](https://packagist.org/packages/mghrby/laravel-modular-ddd)
[![PHP Version](https://img.shields.io/packagist/php-v/mghrby/laravel-modular-ddd.svg)](https://packagist.org/packages/mghrby/laravel-modular-ddd)

A comprehensive Laravel package for implementing modular Domain-Driven Design architecture with advanced tooling, performance monitoring, CQRS implementation, and enterprise-grade features.

## 🌟 Features Overview

### Core Architecture
- **🏗️ Complete Modular DDD**: Vertical slice modules with Domain, Application, Infrastructure, and Presentation layers
- **🔄 Dynamic Module Management**: Install, enable, disable, and remove modules without system disruption
- **🔗 Dependency Resolution**: Automatic dependency management with version constraints and conflict detection
- **📡 Event-Driven Communication**: Inter-module communication through domain events and service contracts

### Advanced Development Tools
- **⚡ CQRS Command/Query Bus**: Built-in CQRS implementation with validation, caching, and handler auto-registration
- **🛠️ API Scaffolding**: Complete REST API generation with authentication, validation, and Swagger documentation
- **🔄 Enterprise API Versioning**: Multi-strategy version negotiation with backward compatibility and deprecation management
- **🎯 Domain Events System**: Event sourcing support with automatic event discovery and listener generation
- **🗄️ Database Migration Generation**: Module-specific migration creation with table creation and modification support
- **✅ Validation Rule Generation**: Custom validation rule scaffolding with proper module namespace organization
- **🧪 Automated Testing**: Intelligent test generation for unit, feature, and integration tests with factory support

### Performance & Monitoring
- **📊 Query Performance Analysis**: Real-time query monitoring with N+1 detection and optimization recommendations
- **💾 Cache Performance Monitoring**: Hit/miss rate tracking with intelligent cache warming suggestions
- **📈 Resource Usage Tracking**: Module memory, disk, and execution time monitoring with threshold alerts
- **🚀 Performance Middleware**: Comprehensive HTTP request performance analysis with automatic reporting

### Security & Authorization
- **🔐 Module Authorization System**: Fine-grained permission management with role-based access control
- **🛡️ Security Scanner**: Automated security vulnerability detection and recommendations
- **🔍 Health Monitoring**: Comprehensive module health checks with dependency validation
- **✅ Code Quality Analysis**: Automated code quality assessment and improvement suggestions

## 📦 Installation

### Requirements
- PHP 8.2+
- Laravel 11.0+
- Composer 2.0+

### Install via Composer

```bash
composer require mghrby/laravel-modular-ddd
```

### Publish Configuration

```bash
# Publish configuration files
php artisan vendor:publish --provider="TaiCrm\LaravelModularDdd\ModularDddServiceProvider" --tag="config"

# Publish stub templates (optional)
php artisan vendor:publish --provider="TaiCrm\LaravelModularDdd\ModularDddServiceProvider" --tag="stubs"
```

### Database Setup

```bash
# Create required tables for authorization system
php artisan migrate
```

## 🚀 Quick Start Guide

### 1. Create Your First Module

```bash
# Generate a complete module with DDD structure
php artisan module:make UserModule --aggregate=User

# Generate complete REST API for the module
php artisan module:make-api UserModule User --auth --validation --swagger

# Install and enable the module
php artisan module:install UserModule
php artisan module:enable UserModule
```

### 2. Module Structure Created

```
modules/UserModule/
├── manifest.json              # Module configuration and dependencies
├── Config/
│   └── permissions.php        # Module permissions configuration
├── Domain/
│   ├── Entities/             # Domain entities and aggregates
│   ├── ValueObjects/         # Value objects
│   ├── Events/               # Domain events
│   ├── Services/             # Domain services
│   └── Contracts/            # Domain interfaces
├── Application/
│   ├── Commands/             # CQRS commands
│   ├── Queries/              # CQRS queries
│   ├── Handlers/             # Command and query handlers
│   └── Services/             # Application services
├── Infrastructure/
│   ├── Repositories/         # Data repositories
│   ├── External/             # External service integrations
│   └── Persistence/          # Database models
├── Presentation/
│   └── Http/
│       ├── Controllers/      # HTTP controllers
│       ├── Requests/         # Form request validation
│       ├── Resources/        # API resources
│       └── Middleware/       # HTTP middleware
├── Database/
│   ├── Migrations/           # Database migrations
│   ├── Seeders/              # Database seeders
│   └── Factories/            # Model factories
├── Routes/
│   ├── api.php              # API routes
│   └── web.php              # Web routes
├── Tests/
│   ├── Unit/                # Unit tests
│   ├── Feature/             # Feature tests
│   └── Integration/         # Integration tests
├── Policies/                # Authorization policies
└── Docs/                    # Module documentation
```

### 3. Check Module Health

```bash
# Comprehensive health check
php artisan module:health UserModule

# Performance analysis
php artisan module:performance:analyze --module=UserModule

# Security scan
php artisan module:security UserModule
```

## 📋 Complete Command Reference

### 🏗️ Module Management Commands

#### Core Module Operations
```bash
# List all modules with detailed status
php artisan module:list

# Create new module with full DDD structure
php artisan module:make {ModuleName} [--aggregate={AggregateName}]

# Install module and resolve dependencies
php artisan module:install {ModuleName}

# Enable module and register services
php artisan module:enable {ModuleName}

# Disable module safely
php artisan module:disable {ModuleName}

# Remove module and cleanup
php artisan module:remove {ModuleName}

# Show detailed module status
php artisan module:status {ModuleName}

# Update module to newer version
php artisan module:update {ModuleName}
php artisan module:update --all

# Create and restore backups
php artisan module:backup {ModuleName}
php artisan module:restore {BackupFile}
```

### 🛠️ Code Generation Commands

#### CQRS Components
```bash
# Create CQRS command with validation
php artisan module:make-command {Module} {CommandName} [--aggregate={Name}] [--validation]

# Create CQRS query with caching
php artisan module:make-query {Module} {QueryName} [--aggregate={Name}] [--cacheable]

# Examples:
php artisan module:make-command UserModule CreateUser --aggregate=User --validation
php artisan module:make-query UserModule GetUser --aggregate=User --cacheable
```

#### Complete API Scaffolding
```bash
# Generate complete REST API with all components and versioning
php artisan module:make-api {Module} {Resource} [--auth] [--validation] [--swagger] [--version={v1|v2}] [--all-versions]

# Generate individual API components
php artisan module:make-controller {Module} {Controller} [--api] [--resource={Model}] [--middleware={Name}]
php artisan module:make-request {Module} {Request} [--resource={Name}] [--validation]
php artisan module:make-resource {Module} {Resource} [--collection] [--model={Name}]
php artisan module:make-middleware {Module} {Middleware} [--auth] [--rate-limit] [--cors]

# Examples:
php artisan module:make-api UserModule User --auth --validation --swagger
php artisan module:make-api UserModule User --auth --validation --swagger --version=v2
php artisan module:make-api UserModule User --auth --validation --swagger --all-versions
php artisan module:make-controller UserModule UserController --api --resource=User
php artisan module:make-request UserModule CreateUserRequest --validation
php artisan module:make-resource UserModule UserResource --model=User
```

#### Domain Components
```bash
# Create domain events and listeners
php artisan module:make-event {Module} {EventName} [--aggregate={Name}]
php artisan module:make-listener {Module} {ListenerName} [--event={EventName}]

# Examples:
php artisan module:make-event UserModule UserRegistered --aggregate=User
php artisan module:make-listener UserModule SendWelcomeEmail --event=UserRegistered
```

#### Testing Components
```bash
# Generate intelligent tests
php artisan module:make-test {Module} {TestName} [--type={unit|feature|integration}]
php artisan module:make-factory {Module} {ModelName}

# Examples:
php artisan module:make-test UserModule UserServiceTest --type=unit
php artisan module:make-test UserModule UserApiTest --type=feature
php artisan module:make-factory UserModule User
```

#### Authorization Components
```bash
# Create authorization policies
php artisan module:make-policy {Module} {PolicyName} [--model={Name}] [--resource] [--api]

# Examples:
php artisan module:make-policy UserModule UserPolicy --model=User --resource
php artisan module:make-policy UserModule UserApiPolicy --model=User --resource --api
```

#### Database Components
```bash
# Create database migrations
php artisan module:make-migration {Module} {MigrationName} [--create={table}] [--table={table}]

# Examples:
php artisan module:make-migration UserModule CreateUsersTable --create=users
php artisan module:make-migration UserModule AddEmailToUsersTable --table=users
php artisan module:make-migration UserModule UpdateUserPermissions
```

#### Validation Components
```bash
# Create custom validation rules
php artisan module:make-rule {Module} {RuleName}

# Examples:
php artisan module:make-rule UserModule Uppercase
php artisan module:make-rule UserModule ValidEmail
php artisan module:make-rule UserModule StrongPassword
```

### 🔄 API Versioning Commands

#### Version Management
```bash
# Check available API versions
curl -H "Accept: application/json" http://localhost/api/versions

# Get module-specific version information
curl -H "Accept: application/json" http://localhost/api/modules/{module}/versions

# Test different version negotiation strategies
curl -H "Accept-Version: v2" http://localhost/api/users                    # Header-based
curl http://localhost/api/v2/users                                          # URL-based
curl http://localhost/api/users?api_version=v2                             # Query parameter
curl -H "Accept: application/vnd.api+json;version=2" http://localhost/api/users  # Content negotiation
```

#### API Version Generation
```bash
# Generate API for specific version
php artisan module:make-api UserModule User --version=v2 --auth --validation --swagger

# Generate API for all supported versions
php artisan module:make-api UserModule User --all-versions --auth --validation --swagger

# The above commands will create:
# - Http/Controllers/Api/v1/UserController.php
# - Http/Controllers/Api/v2/UserController.php
# - Routes/api.php (with versioned routes)
# - Docs/v1/UserApi.php (version-specific documentation)
# - Docs/v2/UserApi.php
```

#### Version Discovery & Testing
```bash
# Test version negotiation
curl -i -H "Accept-Version: v1" http://localhost/api/users
# Response headers will include:
# X-API-Version: v1
# X-API-Supported-Versions: v1, v2
# Warning: 299 - "This API version (v1) is deprecated. It will be sunset on 2025-12-31."

# Test unsupported version
curl -i -H "Accept-Version: v99" http://localhost/api/users
# Returns HTTP 406 with supported versions list
```

### 📚 API Documentation & Swagger Commands

The package provides comprehensive Swagger/OpenAPI 3.0.3 documentation with dedicated management commands for scanning, generating, and validating API documentation across all modules.

#### 🔍 Swagger Management Commands

**Scan Modules for Swagger Annotations:**
```bash
# Scan all modules for Swagger annotations
php artisan module:swagger:scan

# Scan specific module
php artisan module:swagger:scan BlogModule

# Scan with export to files
php artisan module:swagger:scan --export --format=json --output=storage/api-docs

# Generate combined documentation and Swagger UI
php artisan module:swagger:scan --export --combined --ui --format=json
```

**Generate Comprehensive Documentation Files:**
```bash
# Generate documentation for all modules
php artisan module:swagger:generate

# Generate for specific module with UI
php artisan module:swagger:generate BlogModule --ui --output=public/docs

# Generate in YAML format with combined docs
php artisan module:swagger:generate --format=yaml --combined --individual

# Serve documentation locally
php artisan module:swagger:generate --serve --port=8080
```

**Validate Documentation Quality:**
```bash
# Validate all module documentation
php artisan module:swagger:validate

# Validate specific module with strict mode
php artisan module:swagger:validate BlogModule --strict

# Auto-fix common issues
php artisan module:swagger:validate --fix

# Generate validation report
php artisan module:swagger:validate --report=validation-report.json
```

#### 🚀 Enhanced API Generation with Comprehensive Swagger

When you generate APIs using the `module:make-api` command, the package now creates comprehensive Swagger documentation by default:

```bash
# Generate API with comprehensive Swagger documentation (default behavior)
php artisan module:make-api BlogModule Post --auth --validation --swagger

# The above generates:
# ✅ Full OpenAPI 3.0.3 annotations
# ✅ Authentication schemes (Bearer, OAuth2)
# ✅ Comprehensive parameter documentation
# ✅ Response examples with proper HTTP status codes
# ✅ Error response handling (400, 401, 403, 404, 422, 500)
# ✅ HATEOAS links for resource navigation
# ✅ Schema definitions with validation rules
# ✅ Request/response examples with realistic data
```

#### 📊 Generated Documentation Features

**Comprehensive OpenAPI 3.0.3 Annotations:**
- **Detailed Parameter Documentation**: Query parameters, path parameters, request bodies
- **Authentication Integration**: Bearer tokens, OAuth2 flows, API key support
- **Response Examples**: Success and error responses with realistic data
- **Schema Definitions**: Automatic model schema extraction from validation rules
- **HATEOAS Support**: Hypermedia links for API navigation
- **Error Handling**: Standard HTTP error responses with proper descriptions

**Example Generated Controller:**
```php
/**
 * @OA\Get(
 *     path="/api/v1/posts",
 *     operationId="listPostsv1",
 *     tags={"Post"},
 *     summary="Get paginated list of Posts",
 *     description="Retrieve a paginated list of Posts with optional filtering, sorting, and pagination parameters.",
 *     security={{"bearerAuth": {}}, {"oauth2": {}}},
 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", required=false, @OA\Schema(type="integer", minimum=1, default=1, example=1)),
 *     @OA\Parameter(name="per_page", in="query", description="Number of items per page", required=false, @OA\Schema(type="integer", minimum=1, maximum=100, default=15, example=15)),
 *     @OA\Response(
 *         response=200,
 *         description="Successfully retrieved Posts",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Post")),
 *             @OA\Property(property="links", type="object", example={"first": "http://localhost/api/v1/posts?page=1", "last": "http://localhost/api/v1/posts?page=5", "prev": null, "next": "http://localhost/api/v1/posts?page=2"}),
 *             @OA\Property(property="meta", type="object", example={"current_page": 1, "from": 1, "last_page": 5, "per_page": 15, "to": 15, "total": 67})
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated."))),
 *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(@OA\Property(property="message", type="string", example="This action is unauthorized."))),
 *     @OA\Response(response=500, description="Internal Server Error", @OA\JsonContent(@OA\Property(property="message", type="string", example="Internal server error occurred")))
 * )
 */
public function index(): JsonResponse
{
    // Controller implementation
}
```

#### 🎯 Accessing Generated Documentation

**Local Documentation Server:**
```bash
# Start documentation server
php artisan module:swagger:generate --serve --port=8080

# Access documentation at:
# http://localhost:8080/api-documentation-ui.html
```

**File-based Documentation:**
- **JSON Format**: `storage/api-docs/ModuleName.json`
- **YAML Format**: `storage/api-docs/ModuleName.yaml`
- **Swagger UI**: `storage/api-docs/ModuleName-ui.html`
- **Combined Docs**: `storage/api-docs/api-documentation.json`

#### ⚙️ Swagger Configuration

Configure comprehensive documentation in `config/modular-ddd.php`:

```php
'api' => [
    'documentation' => [
        'swagger' => [
            // Enable comprehensive documentation by default
            'enabled' => env('MODULAR_DDD_SWAGGER_ENABLED', true),

            // Documentation metadata
            'title' => env('MODULAR_DDD_SWAGGER_TITLE', 'Laravel Modular DDD API'),
            'description' => env('MODULAR_DDD_SWAGGER_DESCRIPTION', 'Comprehensive API documentation for modular application'),
            'version' => env('MODULAR_DDD_SWAGGER_VERSION', '1.0.0'),

            // Contact information
            'contact' => [
                'name' => env('MODULAR_DDD_SWAGGER_CONTACT_NAME', 'API Support'),
                'url' => env('MODULAR_DDD_SWAGGER_CONTACT_URL', ''),
                'email' => env('MODULAR_DDD_SWAGGER_CONTACT_EMAIL', ''),
            ],

            // Security schemes
            'security' => [
                'bearer_auth' => env('MODULAR_DDD_SWAGGER_BEARER_AUTH', true),
                'oauth2' => env('MODULAR_DDD_SWAGGER_OAUTH2_AUTH', true),
                'api_key' => env('MODULAR_DDD_SWAGGER_API_KEY_AUTH', false),
            ],

            // Generation settings
            'generation' => [
                'comprehensive_by_default' => env('MODULAR_DDD_SWAGGER_COMPREHENSIVE_DEFAULT', true),
                'auto_examples' => env('MODULAR_DDD_SWAGGER_AUTO_EXAMPLES', true),
                'include_hateoas' => env('MODULAR_DDD_SWAGGER_INCLUDE_HATEOAS', true),
                'include_validation_errors' => env('MODULAR_DDD_SWAGGER_INCLUDE_VALIDATION', true),
            ],

            // Export settings
            'export' => [
                'output_dir' => storage_path('api-docs'),
                'json_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ],

            // UI customization
            'ui' => [
                'try_it_out_enabled' => env('MODULAR_DDD_SWAGGER_TRY_IT_OUT', true),
                'deep_linking' => env('MODULAR_DDD_SWAGGER_DEEP_LINKING', true),
                'display_request_duration' => true,
                'doc_expansion' => 'list', // 'list', 'full', 'none'
                'filter' => true,
                'show_extensions' => true,
            ],
        ],
    ],
],
```

#### 🔐 Authentication Integration

**Automatic Authentication Detection:**
- **Bearer Tokens**: JWT and personal access tokens
- **Laravel Passport**: OAuth2 flows with automatic client detection
- **API Keys**: Custom header-based authentication

**OAuth2 Configuration Example:**
```php
// Automatically detected when Laravel Passport is installed
'oauth2_flows' => [
    'authorizationCode' => [
        'authorizationUrl' => config('app.url') . '/oauth/authorize',
        'tokenUrl' => config('app.url') . '/oauth/token',
        'scopes' => [
            '*' => 'Full access to API',
            'read' => 'Read access to resources',
            'write' => 'Write access to resources',
        ],
    ],
    'clientCredentials' => [
        'tokenUrl' => config('app.url') . '/oauth/token',
        'scopes' => [
            '*' => 'Full access to API',
        ],
    ],
],
```

#### 📋 Validation and Quality Assurance

**Documentation Quality Checks:**
```bash
# Comprehensive validation with detailed reporting
php artisan module:swagger:validate --strict --report=quality-report.json

# Validation output example:
# ✅ BlogModule: 4 endpoints, 2 schemas - Good
# ⚠️  UserModule: 3 warnings (missing descriptions)
# ❌ ProductModule: 2 errors (missing required responses)
```

**Quality Metrics:**
- **API Coverage**: Percentage of endpoints with documentation
- **Schema Completeness**: Validation of request/response schemas
- **Security Documentation**: Authentication scheme coverage
- **Error Handling**: Standard HTTP error response coverage
- **Example Completeness**: Request/response example availability

#### 🎨 Advanced Features

**HATEOAS Support:**
```json
{
  "data": {
    "id": 1,
    "title": "Sample Post",
    "content": "Post content here"
  },
  "links": {
    "self": "/api/v1/posts/1",
    "edit": "/api/v1/posts/1",
    "delete": "/api/v1/posts/1",
    "comments": "/api/v1/posts/1/comments"
  }
}
```

**Multi-Version Support:**
```bash
# Generate documentation for all API versions
php artisan module:make-api BlogModule Post --all-versions --swagger

# Generates:
# - v1/PostController.php with v1-specific documentation
# - v2/PostController.php with v2-specific documentation
# - Version-aware Swagger annotations
```

**Custom Schema Definitions:**
- Automatic schema extraction from validation rules
- Support for nested objects and arrays
- Enum values from validation rules
- Custom format validation (email, date, etc.)

#### 🔧 Troubleshooting

**Documentation Not Generating:**
```bash
# Check module status and scan results
php artisan module:swagger:scan ModuleName

# Verify controller annotations
php artisan module:swagger:validate ModuleName --strict

# Clear cache and regenerate
php artisan config:clear && php artisan module:swagger:generate ModuleName
```

**Missing Authentication:**
```bash
# Verify Passport installation
php artisan passport:client --personal

# Check OAuth2 configuration
php artisan route:list | grep oauth

# Validate security schemes
php artisan module:swagger:validate --strict
```

**Quality Issues:**
```bash
# Run comprehensive validation
php artisan module:swagger:validate --strict --fix

# Generate detailed quality report
php artisan module:swagger:validate --report=quality-report.json
```

### 📊 Performance Analysis Commands

#### Comprehensive Performance Analysis
```bash
# Analyze all performance aspects
php artisan module:performance:analyze

# Module-specific analysis
php artisan module:performance:analyze --module={ModuleName}

# Type-specific analysis
php artisan module:performance:analyze --type=queries    # Query performance only
php artisan module:performance:analyze --type=cache      # Cache performance only
php artisan module:performance:analyze --type=resources  # Resource usage only

# Real-time monitoring
php artisan module:performance:analyze --watch --duration=120

# Export analysis results
php artisan module:performance:analyze --export=performance-report.json

# Examples:
php artisan module:performance:analyze --module=UserModule --type=queries
php artisan module:performance:analyze --watch --duration=300
```

### 🔐 Authorization Management Commands

#### Permission Management
```bash
# List all permissions
php artisan module:permission list

# List module-specific permissions
php artisan module:permission list --module={ModuleName}

# Grant permissions to users
php artisan module:permission grant --user={email|id} --module={ModuleName} --permission={PermissionName}

# Revoke permissions from users
php artisan module:permission revoke --user={email|id} --module={ModuleName} --permission={PermissionName}

# Synchronize all module permissions
php artisan module:permission sync

# Show permission matrix
php artisan module:permission matrix
php artisan module:permission matrix --user={email|id}
php artisan module:permission matrix --export=permissions.json

# Examples:
php artisan module:permission grant --user=john@example.com --module=UserModule --permission=view-users
php artisan module:permission matrix --user=admin@example.com --export=admin-permissions.json
```

### 🗄️ Database Operations

```bash
# Run module migrations
php artisan module:migrate {ModuleName}
php artisan module:migrate --all

# Run module seeders
php artisan module:seed {ModuleName}
php artisan module:seed --all

# Rollback module migrations
php artisan module:migrate:rollback {ModuleName}
```

### 🏥 Health & Security Commands

#### Health Monitoring
```bash
# Comprehensive health check
php artisan module:health {ModuleName}
php artisan module:health --all

# Check specific health aspects
php artisan module:health {ModuleName} --dependencies
php artisan module:health {ModuleName} --performance
```

#### Security Analysis
```bash
# Security vulnerability scan
php artisan module:security {ModuleName}
php artisan module:security --all

# Export security report
php artisan module:security {ModuleName} --export=security-report.json
```

### 🔧 Development Tools

#### Cache Management
```bash
# Clear module cache
php artisan module:cache clear

# Rebuild module registry
php artisan module:cache rebuild
```

#### Development Utilities
```bash
# Watch modules for file changes
php artisan module:dev watch

# Generate dependency visualization
php artisan module:visualization

# Show module metrics
php artisan module:metrics {ModuleName}

# Generate DDD components
php artisan module:stub {ComponentType} {Name} {ModuleName}
```

## 💻 Usage Examples

### Basic Module Creation Workflow

```bash
# 1. Create a complete e-commerce product module
php artisan module:make ProductModule --aggregate=Product

# 2. Generate complete API with authentication
php artisan module:make-api ProductModule Product --auth --validation --swagger

# 3. Create additional domain components
php artisan module:make-event ProductModule ProductCreated --aggregate=Product
php artisan module:make-listener ProductModule UpdateInventory --event=ProductCreated

# 4. Generate comprehensive tests
php artisan module:make-test ProductModule ProductServiceTest --type=unit
php artisan module:make-test ProductModule ProductApiTest --type=feature
php artisan module:make-factory ProductModule Product

# 5. Create authorization policy
php artisan module:make-policy ProductModule ProductPolicy --model=Product --resource

# 6. Install and enable the module
php artisan module:install ProductModule
php artisan module:enable ProductModule

# 7. Run health checks and performance analysis
php artisan module:health ProductModule
php artisan module:performance:analyze --module=ProductModule
```

### CQRS Usage in Code

```php
// Using helper functions
$command = new CreateProductCommand('iPhone 15', 999.99, 'Electronics');
$product = dispatch_command($command);

$query = new GetProductQuery($productId);
$productData = ask_query($query);

// Using facades
use TaiCrm\LaravelModularDdd\Foundation\Facades\CommandBus;
use TaiCrm\LaravelModularDdd\Foundation\Facades\QueryBus;

$product = CommandBus::dispatch(new CreateProductCommand($name, $price, $category));
$productData = QueryBus::ask(new GetProductQuery($productId));

// Using dependency injection
class ProductController extends Controller
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus
    ) {}

    public function store(CreateProductRequest $request)
    {
        $command = new CreateProductCommand(...$request->validated());
        $product = $this->commandBus->dispatch($command);

        return new ProductResource($product);
    }
}
```

### API Versioning Usage

```php
// Using version-aware routes in modules/YourModule/Routes/api.php
Route::prefix('api/{version}')
    ->middleware(['api', 'api.version'])
    ->where('version', 'v[1-2]')
    ->group(function () {
        Route::apiResource('users', UserController::class);
    });

// Version-specific controllers
// modules/UserModule/Http/Controllers/Api/V1/UserController.php
class UserController extends Controller
{
    public function index()
    {
        // v1 implementation
        return UserResource::collection(User::all());
    }
}

// modules/UserModule/Http/Controllers/Api/V2/UserController.php
class UserController extends Controller
{
    public function index()
    {
        // v2 implementation with enhanced features
        return UserResource::collection(
            User::with('profile', 'preferences')->get()
        );
    }
}

// Custom version transformations
use TaiCrm\LaravelModularDdd\Http\Compatibility\BaseRequestTransformer;

class UserV1ToV2RequestTransformer extends BaseRequestTransformer
{
    protected function getDescription(): string
    {
        return 'Transform user requests from v1 to v2';
    }

    public function transform(Request $request, string $fromVersion, string $toVersion): Request
    {
        $data = $request->all();

        // Transform old 'full_name' field to 'first_name' and 'last_name'
        if (isset($data['full_name'])) {
            $names = explode(' ', $data['full_name'], 2);
            $data['first_name'] = $names[0];
            $data['last_name'] = $names[1] ?? '';
            unset($data['full_name']);
        }

        return $this->cloneRequest($request)->replace($data);
    }
}

// Register transformers in a service provider
$registry = app(TransformationRegistry::class);
$registry->registerRequestTransformer('v1', 'v2', new UserV1ToV2RequestTransformer('v1', 'v2'));
```

### Authorization Usage

```php
// In your User model
use TaiCrm\LaravelModularDdd\Authorization\Traits\HasModulePermissions;

class User extends Authenticatable
{
    use HasModulePermissions;

    // ... your existing code
}

// Grant permissions programmatically
$user = User::find(1);
$user->grantModulePermission('ProductModule', 'view-products');
$user->grantModulePermissions('ProductModule', ['create-products', 'update-products']);

// Check permissions
if ($user->hasModulePermission('ProductModule', 'view-products')) {
    // User can view products
}

// In routes/web.php or routes/api.php
Route::middleware(['auth', 'module.permission:product-module.view-products'])
    ->get('/products', [ProductController::class, 'index']);

Route::middleware(['auth', 'module.role:product-module.admin'])
    ->delete('/products/{product}', [ProductController::class, 'destroy']);
```

### Performance Monitoring

```php
// Automatic monitoring with middleware
use TaiCrm\LaravelModularDdd\Monitoring\EnhancedPerformanceMiddleware;

// Global middleware in app/Http/Kernel.php
protected $middleware = [
    EnhancedPerformanceMiddleware::class,
];

// Manual performance monitoring
use TaiCrm\LaravelModularDdd\Monitoring\QueryPerformanceAnalyzer;

$analyzer = app(QueryPerformanceAnalyzer::class);
$analyzer->startMonitoring();

// Your database operations
User::with('products')->get();

$report = $analyzer->stopMonitoring();
// Check for slow queries and N+1 issues
$slowQueries = $analyzer->getSlowQueries();
$nPlusOneQueries = $analyzer->detectNPlusOneQueries();
```

## ⚙️ Configuration

### Main Configuration (`config/modular-ddd.php`)

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Modules Path
    |--------------------------------------------------------------------------
    */
    'modules_path' => base_path('modules'),

    /*
    |--------------------------------------------------------------------------
    | Auto Discovery
    |--------------------------------------------------------------------------
    */
    'auto_discovery' => true,

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'enabled' => true,
        'query_threshold' => 1000, // milliseconds
        'memory_threshold' => 128 * 1024 * 1024, // 128MB
        'cache_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Models
    |--------------------------------------------------------------------------
    */
    'models' => [
        'user_module_permission' => 'App\Models\UserModulePermission',
        'user_module_role' => 'App\Models\UserModuleRole',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */
    'security' => [
        'scan_enabled' => true,
        'vulnerability_check' => true,
        'dependency_check' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'query_logging' => true,
        'cache_monitoring' => true,
        'resource_tracking' => true,
        'alerts_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | API Versioning Configuration
    |--------------------------------------------------------------------------
    */
    'api' => [
        'prefix' => 'api',
        'middleware' => ['api'],

        // Multi-version support configuration
        'versions' => [
            'supported' => ['v1', 'v2'],
            'default' => 'v2',
            'latest' => 'v2',
            'deprecated' => ['v1'],
            'sunset_dates' => [
                'v1' => '2025-12-31',
            ],
        ],

        // Version negotiation configuration
        'negotiation' => [
            'strategy' => 'url,header,query,default',
            'headers' => ['Accept-Version', 'X-API-Version'],
        ],

        // Backward compatibility settings
        'compatibility' => [
            'auto_transform' => true,
            'request_transformation' => true,
            'response_transformation' => true,
        ],

        // Documentation and discovery
        'documentation' => [
            'discovery_endpoint' => '/api/versions',
            'include_deprecation_notices' => true,
        ],
    ],
];
```

### Module Permissions Configuration

Create `modules/YourModule/Config/permissions.php`:

```php
<?php

return [
    // Basic CRUD permissions
    'view-products' => [
        'description' => 'View products list and details',
        'group' => 'products',
    ],
    'create-products' => [
        'description' => 'Create new products',
        'group' => 'products',
        'dependencies' => ['view-products'],
    ],
    'update-products' => [
        'description' => 'Update existing products',
        'group' => 'products',
        'dependencies' => ['view-products'],
    ],
    'delete-products' => [
        'description' => 'Delete products',
        'group' => 'products',
        'dependencies' => ['view-products'],
    ],

    // Advanced permissions
    'manage-inventory' => [
        'description' => 'Manage product inventory levels',
        'group' => 'inventory',
        'dependencies' => ['view-products'],
    ],
    'export-products' => [
        'description' => 'Export product data',
        'group' => 'data',
        'dependencies' => ['view-products'],
    ],
    'manage-categories' => [
        'description' => 'Manage product categories',
        'group' => 'administration',
    ],
];
```

## 🗃️ Database Schema

### Required Tables for Authorization

```php
// Create migration: create_user_module_permissions_table
Schema::create('user_module_permissions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('module_id');
    $table->string('permission');
    $table->timestamp('granted_at');
    $table->timestamps();

    $table->unique(['user_id', 'module_id', 'permission']);
    $table->index(['module_id', 'permission']);
});

// Create migration: create_user_module_roles_table
Schema::create('user_module_roles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('module_id');
    $table->string('role');
    $table->timestamp('granted_at');
    $table->timestamps();

    $table->unique(['user_id', 'module_id', 'role']);
    $table->index(['module_id', 'role']);
});
```

## 🏗️ Architecture Principles

### Domain-Driven Design (DDD)
- **Bounded Contexts**: Each module represents a bounded context with clear boundaries
- **Ubiquitous Language**: Consistent terminology throughout each module
- **Aggregate Roots**: Clear aggregate boundaries with proper encapsulation
- **Domain Events**: Event-driven communication between bounded contexts

### CQRS (Command Query Responsibility Segregation)
- **Commands**: Write operations with validation and business logic
- **Queries**: Read operations with caching and optimization
- **Handlers**: Separate handlers for commands and queries
- **Bus Pattern**: Centralized command and query dispatching

### Event Sourcing
- **Domain Events**: Capture state changes as events
- **Event Store**: Persistent storage of domain events
- **Event Replay**: Ability to rebuild state from events
- **Projections**: Read models built from events

### Module Independence
- **Vertical Slices**: Complete feature implementation within modules
- **Service Contracts**: Interface-based communication between modules
- **Dependency Injection**: Loose coupling through dependency injection
- **Event-Driven Communication**: Asynchronous communication via domain events

## 📚 Advanced Features Documentation

### CQRS Implementation
- [Complete CQRS Guide](src/Foundation/README-CQRS.md)
- Command and Query patterns
- Handler registration and discovery
- Validation and caching strategies

### API Scaffolding & Versioning
- [API Scaffolding Guide](src/Commands/README-API-SCAFFOLDING.md)
- REST API generation
- Authentication and authorization
- Swagger documentation
- [API Versioning Guide](docs/api-versioning.md)
- Multi-version support
- Backward compatibility
- Version negotiation strategies

### Performance Monitoring
- [Performance Monitoring Guide](src/Monitoring/README-PERFORMANCE-MONITORING.md)
- Query performance analysis
- Cache optimization
- Resource usage tracking

### Authorization System
- [Authorization Guide](src/Authorization/README-AUTHORIZATION.md)
- Permission management
- Role-based access control
- Policy generation

## 🛡️ Security Best Practices

### Permission Management
- Use fine-grained permissions with clear naming conventions
- Implement permission dependencies to prevent privilege escalation
- Regular permission audits using matrix commands
- Cache permissions for performance while maintaining security

### Input Validation
- Always validate input at the request level
- Use form requests for complex validation rules
- Implement CQRS command validation
- Sanitize data before processing

### Authorization Checks
- Use middleware for route protection
- Implement policy-based authorization for complex logic
- Check permissions at multiple layers (route, controller, service)
- Log authorization failures for security monitoring

## 📈 Performance Optimization

### Query Optimization
- Monitor for N+1 query patterns
- Use eager loading appropriately
- Implement query result caching
- Set appropriate slow query thresholds

### Cache Strategy
- Cache frequently accessed data
- Implement cache warming for critical data
- Monitor cache hit/miss rates
- Use appropriate TTL values

### Resource Management
- Monitor module resource usage
- Set memory and execution time thresholds
- Implement lazy loading for non-critical modules
- Regular performance audits

## 🐛 Troubleshooting

### Common Issues

#### Module Not Loading
```bash
# Check module status
php artisan module:status ModuleName

# Verify dependencies
php artisan module:health ModuleName --dependencies

# Clear and rebuild cache
php artisan module:cache clear
php artisan module:cache rebuild
```

#### Permission Issues
```bash
# Sync permissions
php artisan module:permission sync

# Check user permissions
php artisan module:permission matrix --user=user@example.com

# Verify permission configuration
php artisan module:permission list --module=ModuleName
```

#### Performance Issues
```bash
# Analyze performance
php artisan module:performance:analyze --module=ModuleName

# Check for slow queries
php artisan module:performance:analyze --type=queries

# Monitor in real-time
php artisan module:performance:analyze --watch --duration=300
```

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/your-username/laravel-modular-ddd.git

# Install dependencies
composer install

# Run tests
vendor/bin/phpunit

# Run static analysis
vendor/bin/psalm

# Check code style
vendor/bin/php-cs-fixer fix --dry-run
```

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## 🆘 Support

- **Documentation**: Check the comprehensive guides in the `src/` directories
- **Issues**: Report bugs and request features on [GitHub Issues](https://github.com/your-username/laravel-modular-ddd/issues)
- **Discussions**: Join community discussions on [GitHub Discussions](https://github.com/your-username/laravel-modular-ddd/discussions)

## 🙏 Acknowledgments

- Laravel Framework team for the excellent foundation
- DDD Community for architectural guidance
- Contributors and users who make this package better

---

**Built with ❤️ for the Laravel community**