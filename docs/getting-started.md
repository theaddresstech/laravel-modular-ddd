# Getting Started with Laravel Modular DDD

This guide will help you get started with Laravel Modular DDD package and create your first modular application.

## Installation

Install the package via Composer:

```bash
composer require tai-crm/laravel-modular-ddd
```

### Publish Configuration

Publish the package configuration to customize module paths and settings:

```bash
php artisan vendor:publish --provider="TaiCrm\LaravelModularDdd\Providers\ModularDddServiceProvider" --tag="config"
```

This creates `config/modular-ddd.php` where you can configure:

- `modules_path` - Directory where modules are stored (default: `base_path('modules')`)
- `registry_storage` - Where module registry is stored (default: `storage_path('app/modules')`)
- `auto_discovery` - Enable automatic module discovery (default: `true`)
- Cache settings and validation rules

### Publish Stubs (Optional)

To customize module generation templates:

```bash
php artisan vendor:publish --provider="TaiCrm\LaravelModularDdd\Providers\ModularDddServiceProvider" --tag="stubs"
```

## Your First Module

### Create a Module

Generate a new module with complete DDD structure:

```bash
php artisan module:make Catalog --aggregate=Product --author="Your Name" --description="Product catalog management"
```

This creates a module at `modules/Catalog/` with the following structure:

```
modules/Catalog/
├── manifest.json              # Module configuration
├── Domain/                   # Pure business logic
│   ├── Models/              # Aggregates and entities
│   ├── ValueObjects/        # Value objects
│   ├── Events/              # Domain events
│   ├── Services/            # Domain services
│   └── Repositories/        # Repository interfaces
├── Application/             # Use cases and orchestration
│   ├── Commands/           # Command handlers
│   ├── Queries/            # Query handlers
│   ├── DTOs/               # Data transfer objects
│   └── Services/           # Application services
├── Infrastructure/          # External concerns
│   ├── Persistence/        # Database repositories
│   ├── External/           # External APIs
│   └── Cache/              # Caching implementations
├── Presentation/           # User interfaces
│   ├── Http/              # Controllers and resources
│   └── Console/           # Console commands
├── Database/              # Database files
│   ├── Migrations/        # Database migrations
│   ├── Seeders/          # Database seeders
│   └── Factories/        # Model factories
├── Routes/               # Route definitions
│   ├── api.php          # API routes
│   └── web.php          # Web routes
├── Resources/           # Assets and views
├── Tests/              # Module tests
└── Providers/          # Service providers
```

### Install the Module

Install the module to make it available:

```bash
php artisan module:install Catalog
```

### Enable the Module

Enable the module to activate its functionality:

```bash
php artisan module:enable Catalog
```

### Run Migrations

Create and run database migrations for your module:

```bash
# Create a migration
php artisan make:migration create_products_table --path=modules/Catalog/Database/Migrations

# Run module migrations
php artisan module:migrate Catalog
```

## Understanding the Generated Code

### Domain Layer Example

The generated aggregate root (`modules/Catalog/Domain/Models/Product.php`):

```php
<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Models;

use Modules\Catalog\Domain\ValueObjects\ProductId;
use TaiCrm\LaravelModularDdd\Foundation\AggregateRoot;

class Product extends AggregateRoot
{
    public function __construct(
        private ProductId $id,
        private string $name
    ) {
        parent::__construct();
    }

    public static function create(ProductId $id, string $name): self
    {
        $instance = new self($id, $name);

        // Domain events can be recorded here
        // $instance->recordEvent(new ProductCreated($id, $name));

        return $instance;
    }

    public function getId(): ProductId
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function changeName(string $name): void
    {
        if ($this->name === $name) {
            return;
        }

        $this->name = $name;

        // Record domain event
        // $this->recordEvent(new ProductNameChanged($this->id, $name));
    }
}
```

### Repository Pattern

The repository interface (`modules/Catalog/Domain/Repositories/ProductRepositoryInterface.php`):

```php
<?php

namespace Modules\Catalog\Domain\Repositories;

use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\ValueObjects\ProductId;
use Illuminate\Support\Collection;

interface ProductRepositoryInterface
{
    public function save(Product $product): void;
    public function findById(ProductId $id): ?Product;
    public function findAll(): Collection;
    public function remove(ProductId $id): void;
    public function exists(ProductId $id): bool;
}
```

### Service Provider Integration

The module service provider (`modules/Catalog/Providers/CatalogServiceProvider.php`) automatically:

- Binds repository interfaces to implementations
- Registers services in the service registry
- Sets up event listeners
- Loads migrations and routes

## Module Management Commands

### List Modules

```bash
# List all modules
php artisan module:list

# List only enabled modules
php artisan module:list --enabled

# List only disabled modules
php artisan module:list --disabled
```

### Module Lifecycle

```bash
# Install a module
php artisan module:install ModuleName

# Enable a module
php artisan module:enable ModuleName

# Disable a module
php artisan module:disable ModuleName

# Remove a module completely
php artisan module:remove ModuleName
```

### Module Information

```bash
# Get detailed status of a module
php artisan module:status ModuleName

# Check health of all modules
php artisan module:health --all

# Check health of specific module
php artisan module:health ModuleName
```

### Database Operations

```bash
# Run migrations for all enabled modules
php artisan module:migrate --all

# Run migrations for specific module
php artisan module:migrate ModuleName

# Rollback migrations
php artisan module:migrate ModuleName --rollback

# Seed all enabled modules
php artisan module:seed --all

# Seed specific module
php artisan module:seed ModuleName
```

### Cache Management

```bash
# Clear module cache
php artisan module:cache clear

# Rebuild module cache
php artisan module:cache rebuild
```

## Next Steps

1. **Explore the Architecture Guide**: Learn about DDD principles and modular patterns
2. **Module Development Guide**: Deep dive into creating complex modules
3. **Inter-Module Communication**: Understand how modules communicate
4. **Testing Guide**: Learn how to test modular applications
5. **Deployment Guide**: Deploy modular applications effectively

## Quick Example: Building a Simple Product Catalog

Let's build a complete example:

1. **Create the module**:
```bash
php artisan module:make Catalog --aggregate=Product
```

2. **Create a migration**:
```bash
php artisan make:migration create_products_table --path=modules/Catalog/Database/Migrations
```

3. **Install and enable**:
```bash
php artisan module:install Catalog
php artisan module:enable Catalog
php artisan module:migrate Catalog
```

4. **Test the API**:
```bash
# The generated controller provides REST endpoints:
# GET    /api/catalog/product     - List products
# POST   /api/catalog/product     - Create product
# GET    /api/catalog/product/{id} - Get product
# PUT    /api/catalog/product/{id} - Update product
# DELETE /api/catalog/product/{id} - Delete product
```

Your modular Laravel application is now ready! Each module is completely self-contained with its own domain logic, database structure, and API endpoints.