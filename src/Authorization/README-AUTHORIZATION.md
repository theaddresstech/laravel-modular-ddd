# Module Authorization System

This comprehensive authorization system provides fine-grained permission management for modular DDD applications, integrating seamlessly with Laravel's built-in authorization features.

## Core Components

### 🔐 ModuleAuthorizationManager
Central manager for all module-level permissions and roles.

**Features:**
- Module-specific permission registration
- Role-based access control
- Policy auto-discovery
- Permission dependency validation
- Caching for performance

### 🛡️ Middleware
Protect routes with module-specific authorization.

**Available Middleware:**
- `ModulePermissionMiddleware` - Check specific permissions
- `ModuleRoleMiddleware` - Check role-based access

### 👤 User Traits
Extend your User model with module authorization capabilities.

**Trait:** `HasModulePermissions`
- Permission granting/revoking
- Role management
- Access checking utilities

## Quick Start

### 1. Setup User Model

```php
use TaiCrm\LaravelModularDdd\Authorization\Traits\HasModulePermissions;

class User extends Authenticatable
{
    use HasModulePermissions;

    // Your existing user model code...
}
```

### 2. Create Module Permissions File

Create `modules/YourModule/Config/permissions.php`:

```php
<?php

return [
    // Basic CRUD permissions
    'view-users' => [
        'description' => 'View users list',
        'group' => 'users',
    ],
    'create-users' => [
        'description' => 'Create new users',
        'group' => 'users',
        'dependencies' => ['view-users'],
    ],
    'update-users' => [
        'description' => 'Update existing users',
        'group' => 'users',
        'dependencies' => ['view-users'],
    ],
    'delete-users' => [
        'description' => 'Delete users',
        'group' => 'users',
        'dependencies' => ['view-users'],
    ],

    // Advanced permissions
    'manage-roles' => [
        'description' => 'Manage user roles and permissions',
        'group' => 'administration',
        'dependencies' => ['view-users'],
    ],
    'export-data' => [
        'description' => 'Export module data',
        'group' => 'data',
    ],
];
```

### 3. Create Authorization Policies

```bash
# Generate a resource policy
php artisan module:make-policy UserModule UserPolicy --model=User --resource

# Generate an API-specific policy
php artisan module:make-policy UserModule UserApiPolicy --model=User --resource --api
```

### 4. Protect Routes

```php
use TaiCrm\LaravelModularDdd\Authorization\Middleware\ModulePermissionMiddleware;

// Protect with specific permission
Route::get('/users', [UserController::class, 'index'])
    ->middleware(['auth', ModulePermissionMiddleware::class.':user-module.view-users']);

// Protect with role
Route::post('/users', [UserController::class, 'store'])
    ->middleware(['auth', 'module.role:user-module.admin']);

// Protect entire module access
Route::prefix('admin')->middleware(['auth', 'module.permission:user-module.*'])->group(function () {
    Route::resource('users', UserController::class);
});
```

## Permission Management Commands

### List Permissions

```bash
# List all permissions
php artisan module:permission list

# List permissions for specific module
php artisan module:permission list --module=UserModule
```

### Grant/Revoke Permissions

```bash
# Grant permission to user
php artisan module:permission grant --user=john@example.com --module=UserModule --permission=view-users

# Revoke permission from user
php artisan module:permission revoke --user=john@example.com --module=UserModule --permission=view-users
```

### Permission Matrix

```bash
# Show system permission matrix
php artisan module:permission matrix

# Show user-specific permission matrix
php artisan module:permission matrix --user=john@example.com

# Export matrix to file
php artisan module:permission matrix --user=john@example.com --export=user-permissions.json
```

### Sync Permissions

```bash
# Synchronize all module permissions
php artisan module:permission sync
```

## Advanced Usage

### Programmatic Permission Management

```php
use TaiCrm\LaravelModularDdd\Authorization\ModuleAuthorizationManager;

$authManager = app(ModuleAuthorizationManager::class);

// Check module access
if ($authManager->checkModuleAccess($user, 'UserModule')) {
    // User has access to UserModule
}

// Get user permissions for module
$permissions = $authManager->getUserModulePermissions($user, 'UserModule');

// Check specific permission
if ($authManager->hasPermission($user, 'UserModule', 'view-users')) {
    // User can view users
}
```

### User Permission Management

```php
$user = User::find(1);

// Grant permissions
$user->grantModulePermission('UserModule', 'view-users');
$user->grantModulePermissions('UserModule', ['create-users', 'update-users']);

// Check permissions
if ($user->hasModulePermission('UserModule', 'view-users')) {
    // User has permission
}

// Get all module permissions
$permissions = $user->getModulePermissionsFor('UserModule');

// Grant/revoke roles
$user->grantModuleRole('UserModule', 'admin');
$user->revokeModuleRole('UserModule', 'viewer');

// Sync permissions (replace all)
$user->syncModulePermissions('UserModule', ['view-users', 'create-users']);
```

### Policy-Based Authorization

```php
// In your generated policy
class UserPolicy
{
    public function view($user, User $targetUser): bool
    {
        // User can view if they have view permission
        if ($user->hasModulePermission('user-module', 'view-users')) {
            return true;
        }

        // Or if they're viewing their own profile
        return $user->id === $targetUser->id;
    }

    public function update($user, User $targetUser): bool
    {
        // Check update permission
        if ($user->hasModulePermission('user-module', 'update-users')) {
            return true;
        }

        // Allow users to edit their own profile
        return $user->id === $targetUser->id &&
               $user->hasModulePermission('user-module', 'edit-own-profile');
    }
}
```

### Controller Authorization

```php
class UserController extends Controller
{
    public function index(Request $request)
    {
        // Check permission via middleware or manually
        $this->authorize('viewAny', User::class);

        // Or check module permission directly
        if (!auth()->user()->hasModulePermission('user-module', 'view-users')) {
            abort(403, 'Insufficient permissions');
        }

        return UserResource::collection(User::paginate());
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);
        return new UserResource($user);
    }

    public function store(CreateUserRequest $request)
    {
        $this->authorize('create', User::class);

        // Create user logic...
    }
}
```

### API Resource Authorization

```php
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        $user = auth()->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->when(
                $user->hasModulePermission('user-module', 'view-sensitive-data'),
                $this->email
            ),
            'roles' => $this->when(
                $user->hasModulePermission('user-module', 'view-roles'),
                $this->roles
            ),
            'permissions' => [
                'can_edit' => $user->can('update', $this->resource),
                'can_delete' => $user->can('delete', $this->resource),
            ],
        ];
    }
}
```

## Middleware Usage

### Route Protection

```php
// Protect with permission
Route::middleware(['auth', 'module.permission:user-module.view-users'])
    ->get('/users', [UserController::class, 'index']);

// Protect with role
Route::middleware(['auth', 'module.role:user-module.admin'])
    ->delete('/users/{user}', [UserController::class, 'destroy']);

// Multiple roles (OR logic)
Route::middleware(['auth', 'module.role:user-module.admin|user-module.moderator'])
    ->patch('/users/{user}', [UserController::class, 'update']);

// Wildcard permission (any permission in module)
Route::middleware(['auth', 'module.permission:user-module.*'])
    ->prefix('admin')->group(function () {
        // Admin routes
    });
```

### Middleware Registration

```php
// In app/Http/Kernel.php
protected $routeMiddleware = [
    // ... other middleware
    'module.permission' => \TaiCrm\LaravelModularDdd\Authorization\Middleware\ModulePermissionMiddleware::class,
    'module.role' => \TaiCrm\LaravelModularDdd\Authorization\Middleware\ModuleRoleMiddleware::class,
];
```

## Database Schema

### Required Tables

Create these tables for storing module permissions:

```php
// Migration for user_module_permissions table
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

// Migration for user_module_roles table
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

## Best Practices

### 1. **Permission Naming Convention**
- Use kebab-case: `view-users`, `create-posts`
- Be specific: `view-sensitive-data` instead of `view-data`
- Group related permissions: `users.*`, `admin.*`

### 2. **Role Hierarchy**
```php
// Define clear role hierarchy
'roles' => [
    'viewer' => ['view-users'],
    'editor' => ['view-users', 'create-users', 'update-users'],
    'admin' => ['view-users', 'create-users', 'update-users', 'delete-users', 'manage-roles'],
    'super-admin' => ['*'], // All permissions
]
```

### 3. **Permission Dependencies**
```php
'create-users' => [
    'description' => 'Create new users',
    'dependencies' => ['view-users'], // Must have view permission first
],
```

### 4. **Performance Optimization**
- Cache permissions using the built-in caching system
- Use middleware for route protection instead of checking in controllers
- Batch permission checks when possible

### 5. **Security Considerations**
- Always validate permissions on the server side
- Use policies for complex authorization logic
- Implement permission dependencies to prevent privilege escalation
- Regular permission audits using the matrix command

This authorization system provides enterprise-grade security features while maintaining flexibility and performance for modular Laravel applications.