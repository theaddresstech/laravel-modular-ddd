# Architecture Overview

Laravel Modular DDD implements a sophisticated modular architecture based on Domain-Driven Design (DDD) principles, inspired by Odoo's module system. This guide explains the core architectural concepts and patterns.

## Core Principles

### 1. Modular Independence

Each module is a complete vertical slice containing all architectural layers:

- **Self-Contained**: Modules include their own domain logic, database, routes, and views
- **Plug-and-Play**: Modules can be added, removed, or disabled without affecting others
- **Independent Deployment**: Modules can be developed and deployed separately
- **Version Control**: Each module maintains its own version and dependencies

### 2. Domain-Driven Design

The architecture follows DDD tactical patterns:

- **Bounded Contexts**: Each module represents a distinct business domain
- **Aggregate Roots**: Ensure consistency boundaries and transaction integrity
- **Value Objects**: Immutable objects representing domain concepts
- **Domain Events**: Enable loose coupling between domains
- **Repository Pattern**: Abstract data access concerns

### 3. Clean Architecture Layers

Each module follows the same layered architecture:

```
┌─────────────────────────────────────┐
│           Presentation              │ ← Controllers, CLI, Views
├─────────────────────────────────────┤
│           Application               │ ← Use Cases, Commands, Queries
├─────────────────────────────────────┤
│           Infrastructure            │ ← Database, External APIs, Cache
├─────────────────────────────────────┤
│             Domain                  │ ← Business Logic, Entities, Rules
└─────────────────────────────────────┘
```

#### Dependency Direction

- **Outer layers depend on inner layers, never the reverse**
- Domain layer has no dependencies on infrastructure
- Application layer defines interfaces, infrastructure implements them
- Presentation layer orchestrates but contains no business logic

## Module Structure Deep Dive

### Domain Layer

The domain layer contains pure business logic with no external dependencies:

```
Domain/
├── Models/              # Aggregates and entities
│   ├── Product.php      # Aggregate root
│   └── Category.php     # Entity
├── ValueObjects/        # Immutable value objects
│   ├── ProductId.php    # Identity value object
│   ├── Money.php        # Business value object
│   └── SKU.php          # Domain value object
├── Events/              # Domain events
│   ├── ProductCreated.php
│   └── PriceChanged.php
├── Services/            # Domain services
│   └── PricingService.php
├── Repositories/        # Repository contracts
│   └── ProductRepositoryInterface.php
├── Specifications/      # Business rules
│   └── ProductAvailableSpec.php
└── Exceptions/         # Domain exceptions
    └── InvalidPriceException.php
```

**Key Patterns:**

- **Aggregate Roots** maintain consistency boundaries
- **Value Objects** are immutable and equality-based
- **Domain Events** capture important business occurrences
- **Specifications** encapsulate complex business rules

### Application Layer

The application layer orchestrates domain operations and use cases:

```
Application/
├── Commands/           # Write operations
│   └── CreateProduct/
│       ├── CreateProductCommand.php
│       └── CreateProductHandler.php
├── Queries/            # Read operations
│   └── GetProduct/
│       ├── GetProductQuery.php
│       └── GetProductHandler.php
├── DTOs/               # Data transfer objects
│   ├── ProductDTO.php
│   └── CreateProductDTO.php
└── Services/           # Application services
    └── ProductApplicationService.php
```

**CQRS Pattern:**

- **Commands** handle write operations that change state
- **Queries** handle read operations without side effects
- **Handlers** contain the use case logic
- **DTOs** transfer data between layers

### Infrastructure Layer

The infrastructure layer handles external concerns:

```
Infrastructure/
├── Persistence/        # Data persistence
│   └── Eloquent/
│       ├── Models/
│       │   └── ProductModel.php
│       └── Repositories/
│           └── EloquentProductRepository.php
├── External/           # External services
│   ├── PaymentGateway/
│   └── EmailService/
├── Cache/              # Caching implementations
│   └── ProductCacheRepository.php
└── Events/             # Event handling
    └── ProductEventHandler.php
```

**Adapter Pattern:**

- Repository implementations adapt domain contracts to specific technologies
- External service adapters isolate third-party dependencies
- Event handlers translate domain events to infrastructure concerns

### Presentation Layer

The presentation layer handles user interactions:

```
Presentation/
├── Http/              # Web interfaces
│   ├── Controllers/
│   │   └── ProductController.php
│   ├── Requests/
│   │   └── CreateProductRequest.php
│   └── Resources/
│       └── ProductResource.php
└── Console/           # CLI interfaces
    └── ImportProductsCommand.php
```

## Inter-Module Communication

Modules communicate through well-defined contracts and events:

### 1. Contract-Based Communication

Modules expose service contracts that other modules can depend on:

```php
// In Catalog module
interface ProductServiceInterface
{
    public function findProduct(ProductId $id): ?Product;
    public function checkAvailability(ProductId $id): bool;
}

// In Order module
class OrderService
{
    public function __construct(
        private ProductServiceInterface $productService
    ) {}
}
```

### 2. Event-Driven Communication

Modules communicate through domain events:

```php
// Publisher (Catalog module)
class Product extends AggregateRoot
{
    public function changePrice(Money $newPrice): void
    {
        $this->price = $newPrice;
        $this->recordEvent(new ProductPriceChanged($this->id, $newPrice));
    }
}

// Subscriber (Pricing module)
class UpdatePricingIndexHandler
{
    public function handle(ProductPriceChanged $event): void
    {
        // Update pricing index
    }
}
```

### 3. Service Registry

The service registry provides runtime service discovery:

```php
// Register service
$registry->register('ProductService', ProductServiceImpl::class, 'Catalog');

// Resolve service
$productService = $registry->resolve('ProductService');
```

## Module Lifecycle

### Installation Process

1. **Discovery**: System scans module directory
2. **Validation**: Validates module structure and manifest
3. **Dependency Check**: Resolves and validates dependencies
4. **Installation**: Copies files and registers module
5. **Migration**: Runs database migrations
6. **Registration**: Updates module registry

### Enabling Process

1. **Dependency Resolution**: Ensures all dependencies are enabled
2. **Service Registration**: Registers services in service registry
3. **Route Loading**: Loads module routes
4. **Event Binding**: Binds event listeners
5. **Provider Loading**: Loads module service providers

### Dependency Management

The system uses topological sorting to resolve dependencies:

```php
// Example dependency chain
ModuleA depends on [ModuleB, ModuleC]
ModuleB depends on [ModuleC]
ModuleC depends on []

// Install order: ModuleC → ModuleB → ModuleA
```

## Caching Strategy

The system implements multi-level caching:

1. **Module Registry Cache**: Caches module states and metadata
2. **Discovery Cache**: Caches module discovery results
3. **Dependency Cache**: Caches dependency resolution
4. **Route Cache**: Leverages Laravel's route caching

## Error Handling

### Exception Hierarchy

```php
ModularDddException
├── ModuleNotFoundException
├── DependencyException
│   ├── MissingDependencyException
│   ├── CircularDependencyException
│   └── ConflictingModuleException
└── ModuleInstallationException
    ├── CannotInstallException
    ├── CannotEnableException
    └── CannotRemoveException
```

### Graceful Degradation

- Missing optional dependencies don't prevent module loading
- Module failures are isolated and logged
- System continues operating with remaining healthy modules

## Performance Considerations

### Lazy Loading

- Modules are loaded only when needed
- Service providers are registered on-demand
- Routes are cached for production

### Memory Management

- Modules release resources when disabled
- Event listeners are unregistered properly
- Caches are cleared when modules change

### Database Optimization

- Each module can have its own database connection
- Migrations run in dependency order
- Database queries are scoped to module boundaries

## Security

### Isolation

- Modules cannot access each other's internals directly
- Communication only through defined contracts
- Database access is scoped to module tables

### Validation

- Module manifests are validated against schema
- Dependencies are checked before installation
- Service contracts are type-checked

This architecture provides a robust foundation for building scalable, maintainable Laravel applications with clear boundaries and strong encapsulation.