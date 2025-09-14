# API Scaffolding Commands

The Laravel Modular DDD package provides comprehensive API scaffolding commands to quickly generate REST API endpoints with proper DDD structure.

## Available Commands

### 1. Complete API Scaffolding
Generate a complete REST API with all components:

```bash
# Basic API scaffolding
php artisan module:make-api UserModule User

# With authentication
php artisan module:make-api UserModule User --auth

# With validation and Swagger docs
php artisan module:make-api UserModule User --validation --swagger

# Complete with all features
php artisan module:make-api UserModule User --auth --validation --swagger
```

**Generated Files:**
- `Http/Controllers/UserController.php` - REST API controller
- `Http/Requests/User/CreateUserRequest.php` - Create validation
- `Http/Requests/User/UpdateUserRequest.php` - Update validation
- `Http/Resources/UserResource.php` - API resource transformer
- `Routes/api.php` - API routes (updated)
- `Application/Commands/` - CQRS commands (Create, Update, Delete)
- `Application/Queries/` - CQRS queries (Get, List)
- `Application/Handlers/` - Command and query handlers
- `Docs/UserApi.php` - Swagger documentation (optional)

### 2. Individual Component Generation

#### Controllers
```bash
# Basic controller
php artisan module:make-controller UserModule UserController

# API controller
php artisan module:make-controller UserModule UserController --api

# API resource controller
php artisan module:make-controller UserModule UserController --api --resource=User

# With middleware
php artisan module:make-controller UserModule UserController --api --middleware=auth:api
```

#### Form Requests
```bash
# Basic request
php artisan module:make-request UserModule CreateUserRequest

# With resource grouping
php artisan module:make-request UserModule CreateUserRequest --resource=User

# With intelligent validation
php artisan module:make-request UserModule CreateUserRequest --validation
```

#### API Resources
```bash
# Single resource
php artisan module:make-resource UserModule UserResource

# Resource collection
php artisan module:make-resource UserModule UserCollection --collection

# With model awareness
php artisan module:make-resource UserModule UserResource --model=User
```

#### Middleware
```bash
# Basic middleware
php artisan module:make-middleware UserModule CustomMiddleware

# Authentication middleware
php artisan module:make-middleware UserModule AuthMiddleware --auth

# Rate limiting middleware
php artisan module:make-middleware UserModule RateLimitMiddleware --rate-limit

# CORS middleware
php artisan module:make-middleware UserModule CorsMiddleware --cors
```

## Features

### 🎯 Smart Code Generation

**Intelligent Templates:**
- Context-aware validation rules based on request names
- Model-specific resource attributes
- Security-focused authorization logic
- DDD-compliant structure

**Example - CreateUserRequest generates:**
```php
public function rules(): array
{
    return [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:8|confirmed',
        'terms' => 'accepted',
    ];
}
```

### 🔒 Security by Default

**Built-in Security Features:**
- CSRF protection for web routes
- Rate limiting middleware templates
- Authentication middleware integration
- Authorization logic in form requests
- Input validation and sanitization

### 🚀 CQRS Integration

**Automatic CQRS Generation:**
- Commands for write operations (Create, Update, Delete)
- Queries for read operations (Get, List)
- Handler classes with proper separation
- Integration with CommandBus and QueryBus

### 📚 API Documentation

**Swagger Support:**
- OpenAPI 3.0 annotations
- Automatic endpoint documentation
- Parameter and response schemas
- Tag-based organization

## Usage Examples

### Complete User Management API

```bash
# Generate complete user API
php artisan module:make-api UserModule User --auth --validation --swagger
```

This generates a complete user management system with:

**Controller (UserController.php):**
```php
class UserController extends Controller
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = new ListUserQuery(
            $request->get('filters', []),
            $request->get('sort', 'created_at'),
            $request->get('direction', 'desc'),
            $request->get('per_page', 15)
        );

        $users = $this->queryBus->ask($query);
        return response()->json([
            'data' => UserResource::collection($users->items()),
            'meta' => [/* pagination */]
        ]);
    }

    public function store(CreateUserRequest $request): JsonResponse
    {
        $command = new CreateUserCommand(...$request->validated());
        $user = $this->commandBus->dispatch($command);

        return response()->json([
            'data' => new UserResource($user),
            'message' => 'User created successfully'
        ], 201);
    }
}
```

**Routes (api.php):**
```php
Route::apiResource('users', UserController::class)->middleware('auth:api');
```

**Available Endpoints:**
- `GET /api/users` - List users with pagination
- `POST /api/users` - Create new user
- `GET /api/users/{id}` - Get specific user
- `PUT /api/users/{id}` - Update user
- `DELETE /api/users/{id}` - Delete user

### Custom Middleware Example

```bash
# Generate auth middleware
php artisan module:make-middleware UserModule UserAccessMiddleware --auth
```

**Generated Middleware:**
```php
class UserAccessMiddleware
{
    public function handle(Request $request, Closure $next, string $guard = null): Response
    {
        if (!Auth::guard($guard)->check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user = Auth::guard($guard)->user();
        // Add additional authorization logic here

        return $next($request);
    }
}
```

### Resource with Relationships

```bash
# Generate user resource with model awareness
php artisan module:make-resource UserModule UserResource --model=User
```

**Generated Resource:**
```php
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'avatar' => $this->avatar,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'relationships' => [
                'when' => $this->whenLoaded('relationships', [
                    // Add related resources here
                ]),
            ],
            'permissions' => [
                'can_edit' => $this->when(auth()->check(), function () {
                    return auth()->user()->can('update', $this->resource);
                }),
                'can_delete' => $this->when(auth()->check(), function () {
                    return auth()->user()->can('delete', $this->resource);
                }),
            ],
        ];
    }
}
```

## Best Practices

### 1. **Consistent Structure**
- Use proper DDD module organization
- Follow Laravel naming conventions
- Implement CQRS pattern for business logic

### 2. **Security First**
- Always validate input data
- Use proper authentication/authorization
- Implement rate limiting for public APIs

### 3. **API Design**
- Use RESTful conventions
- Provide meaningful HTTP status codes
- Include pagination for list endpoints
- Add proper error handling

### 4. **Documentation**
- Generate Swagger documentation
- Keep API docs up to date
- Provide usage examples

### 5. **Testing**
- Write tests for all endpoints
- Test validation rules
- Mock external dependencies

## Integration with Existing Systems

The API scaffolding integrates seamlessly with:

- **CQRS System:** Automatic command/query generation
- **Event System:** Domain events for API actions
- **Testing Framework:** Test generation for all endpoints
- **Security Scanner:** Automatic security analysis
- **Performance Monitor:** Built-in performance tracking

## Command Reference

| Command | Purpose | Options |
|---------|---------|---------|
| `module:make-api` | Complete API scaffolding | `--auth`, `--validation`, `--swagger` |
| `module:make-controller` | Generate controllers | `--api`, `--resource`, `--middleware` |
| `module:make-request` | Form request validation | `--resource`, `--validation` |
| `module:make-resource` | API resource transformers | `--collection`, `--model` |
| `module:make-middleware` | HTTP middleware | `--auth`, `--rate-limit`, `--cors` |

This comprehensive API scaffolding system enables rapid development of secure, well-structured REST APIs following DDD principles and Laravel best practices.