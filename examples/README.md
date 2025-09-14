# Laravel Modular DDD Examples

This directory contains comprehensive examples demonstrating how to build modules using the Laravel Modular DDD package. These examples showcase real-world implementations of Domain-Driven Design principles within a modular architecture.

## 📚 Available Examples

### 1. ProductCatalog Module

A complete product catalog management module that demonstrates:

- **Domain-Driven Design** implementation with proper layering
- **Aggregate Roots** (Product, Category) with business logic encapsulation
- **Value Objects** (Money, ProductId, ProductStatus) with validation
- **Domain Events** for cross-cutting concerns
- **CQRS** pattern with Commands, Queries, and Handlers
- **Repository Pattern** with interfaces and implementations
- **REST API** with proper resource transformations
- **Comprehensive Testing** with unit, integration, and feature tests

#### Features Demonstrated:
- Product creation, modification, and lifecycle management
- Price management with currency support
- Category assignment and organization
- Image and attribute management
- Product publishing workflow
- Event-driven architecture
- Data validation and error handling
- Performance monitoring integration
- Security best practices

#### Module Structure:
```
ProductCatalog/
├── manifest.json              # Module configuration
├── Domain/                    # Business logic layer
│   ├── Models/               # Aggregate Roots and Entities
│   │   └── Product.php       # Main product aggregate
│   ├── ValueObjects/         # Immutable value objects
│   │   ├── ProductId.php     # Identity value object
│   │   ├── Money.php         # Money value object with currency
│   │   └── ProductStatus.php # Status enumeration
│   ├── Events/               # Domain events
│   ├── Repositories/         # Repository interfaces
│   └── Exceptions/           # Domain-specific exceptions
├── Application/              # Use case orchestration
│   ├── Commands/            # Write operations (CQRS)
│   ├── Queries/             # Read operations (CQRS)
│   ├── DTOs/                # Data transfer objects
│   └── Services/            # Application services
├── Infrastructure/          # External interfaces
│   ├── Persistence/         # Database implementations
│   ├── External/            # External service adapters
│   └── Events/             # Event handlers
├── Presentation/           # User interfaces
│   ├── Http/              # API controllers and resources
│   └── Console/           # CLI commands
├── Database/              # Database migrations and seeders
├── Tests/                 # Comprehensive test suite
└── Config/               # Module configuration
```

## 🚀 Getting Started

### 1. Installation

First, ensure you have the Laravel Modular DDD package installed:

```bash
composer require tai-crm/laravel-modular-ddd
```

### 2. Copy Example Modules

Copy the example modules to your project's modules directory:

```bash
# Copy ProductCatalog example
cp -r vendor/tai-crm/laravel-modular-ddd/examples/ProductCatalog modules/

# Install the module
php artisan module:install ProductCatalog
php artisan module:enable ProductCatalog
```

### 3. Run Migrations

```bash
# Run module migrations
php artisan module:migrate ProductCatalog

# Or migrate all modules
php artisan module:migrate --all
```

### 4. Seed Sample Data (Optional)

```bash
php artisan module:seed ProductCatalog
```

### 5. Test the Implementation

```bash
# Run module tests
php artisan test modules/ProductCatalog/Tests

# Check module health
php artisan module:health ProductCatalog
```

## 📖 Learning Path

### Beginner: Understanding the Basics

1. **Start with the Domain Layer**
   - Examine `Domain/Models/Product.php` to understand Aggregate Roots
   - Study `Domain/ValueObjects/` to see immutable value objects
   - Look at `Domain/Events/` for domain event implementation

2. **Application Layer Patterns**
   - Review `Application/Commands/` for CQRS write operations
   - Check `Application/Queries/` for CQRS read operations
   - Study `Application/DTOs/` for data transfer patterns

3. **Infrastructure Implementation**
   - Examine `Infrastructure/Persistence/` for repository implementations
   - Review database migrations and model mappings

### Intermediate: Advanced Patterns

1. **Event-Driven Architecture**
   - Study how domain events are recorded and dispatched
   - Examine event handlers in `Infrastructure/Events/`
   - Understand cross-module communication

2. **Testing Strategies**
   - Unit tests in `Tests/Unit/` for domain logic
   - Integration tests in `Tests/Integration/`
   - Feature tests in `Tests/Feature/` for API endpoints

3. **API Design**
   - Review `Presentation/Http/Controllers/` for REST API implementation
   - Study `Presentation/Http/Resources/` for response transformations
   - Examine request validation in `Presentation/Http/Requests/`

### Advanced: Architecture and Patterns

1. **Module Interactions**
   - Study the manifest.json for dependency declarations
   - Understand service contracts and interfaces
   - Review inter-module communication patterns

2. **Performance and Monitoring**
   - Examine performance monitoring integration
   - Study caching strategies and implementation
   - Review database optimization techniques

## 🧪 Experimental Features

The examples also demonstrate experimental and advanced features:

### 1. Event Sourcing (Partial Implementation)
```php
// Example of event-sourced aggregate reconstitution
$product = Product::fromEvents($events);
```

### 2. Specification Pattern
```php
// Business rules as specifications
$availableSpec = new ProductAvailableSpecification();
$isAvailable = $availableSpec->isSatisfiedBy($product);
```

### 3. Domain Services
```php
// Complex business logic coordination
$pricingService = new ProductPricingService();
$finalPrice = $pricingService->calculatePrice($product, $customer);
```

## 🔧 Customization

### Adapting Examples to Your Domain

1. **Rename and Modify**
   ```bash
   # Use the examples as templates
   cp -r examples/ProductCatalog modules/YourModule

   # Update namespace and class names
   # Modify business logic to match your domain
   ```

2. **Extend with Additional Features**
   - Add new value objects for your domain concepts
   - Implement additional aggregate roots
   - Create domain services for complex business rules
   - Add custom event handlers for integration

3. **Integration with Existing Systems**
   - Implement adapters for external APIs
   - Create custom repository implementations
   - Add authentication and authorization

## 📊 Performance Considerations

The examples include performance optimizations:

- **Database Indexing**: Strategic indexes on frequently queried columns
- **Caching**: Repository-level caching for read operations
- **Query Optimization**: Efficient database queries with proper joins
- **Event Handling**: Asynchronous event processing for better performance

## 🔒 Security Features

Security best practices demonstrated:

- **Input Validation**: Comprehensive request validation
- **SQL Injection Prevention**: Parameterized queries and ORM usage
- **Authorization**: Role-based access control integration
- **Data Sanitization**: Proper data cleaning and validation

## 📈 Monitoring and Observability

Examples include monitoring integrations:

- **Performance Metrics**: Command and query execution timing
- **Health Checks**: Module health monitoring endpoints
- **Logging**: Structured logging with context information
- **Error Tracking**: Comprehensive error handling and reporting

## 🤝 Contributing

To contribute additional examples:

1. **Follow the Structure**: Use the same directory layout and patterns
2. **Include Tests**: Comprehensive test coverage is required
3. **Documentation**: Provide clear documentation and comments
4. **Real-World Scenarios**: Examples should solve actual business problems

## 📚 Additional Resources

- [Architecture Documentation](../docs/architecture.md)
- [Module Development Guide](../docs/module-development.md)
- [Testing Best Practices](../docs/testing-guide.md)
- [Performance Optimization](../docs/performance-guide.md)
- [Security Guidelines](../docs/security-guide.md)

## 🆘 Support

If you need help with the examples:

1. Check the [documentation](../docs/)
2. Review the [FAQ](../docs/faq.md)
3. Open an issue on [GitHub](https://github.com/tai-crm/laravel-modular-ddd/issues)
4. Join our [community discussions](https://github.com/tai-crm/laravel-modular-ddd/discussions)

---

These examples provide a solid foundation for building your own modular applications using Domain-Driven Design principles. Start with the ProductCatalog example and gradually explore more advanced patterns as you become comfortable with the architecture.