# Module Development Guide

This guide covers everything you need to know about developing modules using Laravel Modular DDD.

## Creating Your First Module

### Module Generation

Generate a complete DDD module structure:

```bash
php artisan module:make Catalog --aggregate=Product --author="Your Team" --description="Product catalog management"
```

This creates a fully structured module with:

- **Domain Layer**: Business logic, entities, value objects
- **Application Layer**: Use cases, commands, queries
- **Infrastructure Layer**: Database, external services, caching
- **Presentation Layer**: Controllers, requests, resources

### Module Structure Explained

```
modules/Catalog/
├── manifest.json              # Module configuration
├── Domain/                   # Pure business logic
│   ├── Models/              # Aggregates and entities
│   │   └── Product.php      # Main aggregate root
│   ├── ValueObjects/        # Immutable value objects
│   │   └── ProductId.php    # Identity value object
│   ├── Events/              # Domain events
│   │   └── ProductCreated.php
│   ├── Services/            # Domain services
│   │   └── ProductService.php
│   ├── Repositories/        # Repository interfaces
│   │   └── ProductRepositoryInterface.php
│   └── Specifications/      # Business rules
│       └── ProductAvailableSpec.php
├── Application/             # Use case orchestration
│   ├── Commands/           # Write operations (CQRS)
│   │   └── CreateProduct/
│   │       ├── CreateProductCommand.php
│   │       └── CreateProductHandler.php
│   ├── Queries/            # Read operations (CQRS)
│   │   └── GetProduct/
│   │       ├── GetProductQuery.php
│   │       └── GetProductHandler.php
│   ├── DTOs/               # Data transfer objects
│   │   └── ProductDTO.php
│   └── Services/           # Application services
│       └── ProductApplicationService.php
├── Infrastructure/         # External concerns
│   ├── Persistence/        # Database implementations
│   │   └── Eloquent/
│   │       ├── Models/
│   │       │   └── ProductModel.php
│   │       └── Repositories/
│   │           └── EloquentProductRepository.php
│   ├── External/           # External service adapters
│   ├── Cache/             # Caching implementations
│   └── Events/            # Event handlers
├── Presentation/          # User interfaces
│   ├── Http/             # Web interfaces
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   └── Resources/
│   └── Console/          # CLI commands
├── Database/             # Database files
│   ├── Migrations/       # Schema migrations
│   ├── Seeders/         # Data seeders
│   └── Factories/       # Test factories
├── Routes/              # Route definitions
│   ├── api.php         # API routes
│   └── web.php         # Web routes
├── Resources/           # Assets and views
│   ├── views/          # Blade templates
│   ├── assets/         # CSS, JS, images
│   └── lang/           # Translations
├── Tests/              # Module tests
│   ├── Unit/          # Unit tests
│   ├── Feature/       # Feature tests
│   └── Integration/   # Integration tests
├── Config/            # Module configuration
└── Providers/         # Service providers
    └── CatalogServiceProvider.php
```

## Domain-Driven Design Implementation

### Aggregate Roots

Aggregate roots are the entry points to your domain model:

```php
<?php

namespace Modules\Catalog\Domain\Models;

use Modules\Catalog\Domain\ValueObjects\ProductId;
use Modules\Catalog\Domain\Events\ProductCreated;
use TaiCrm\LaravelModularDdd\Foundation\AggregateRoot;

class Product extends AggregateRoot
{
    private function __construct(
        private ProductId $id,
        private string $name,
        private Money $price,
        private ProductStatus $status
    ) {
        parent::__construct();
    }

    public static function create(
        ProductId $id,
        string $name,
        Money $price
    ): self {
        $product = new self($id, $name, $price, ProductStatus::Draft);

        $product->recordEvent(new ProductCreated(
            $id,
            $name,
            $price,
            now()
        ));

        return $product;
    }

    public function changePrice(Money $newPrice): void
    {
        if ($this->price->equals($newPrice)) {
            return;
        }

        $oldPrice = $this->price;
        $this->price = $newPrice;

        $this->recordEvent(new ProductPriceChanged(
            $this->id,
            $oldPrice,
            $newPrice
        ));
    }

    public function publish(): void
    {
        if ($this->status === ProductStatus::Published) {
            throw new ProductAlreadyPublishedException($this->id);
        }

        $this->status = ProductStatus::Published;

        $this->recordEvent(new ProductPublished($this->id, now()));
    }

    // Getters
    public function getId(): ProductId { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getPrice(): Money { return $this->price; }
    public function getStatus(): ProductStatus { return $this->status; }
}
```

### Value Objects

Value objects represent concepts that are defined by their attributes:

```php
<?php

namespace Modules\Catalog\Domain\ValueObjects;

use TaiCrm\LaravelModularDdd\Foundation\ValueObject;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

readonly class ProductId extends ValueObject
{
    public function __construct(
        private UuidInterface $value
    ) {}

    public static function generate(): self
    {
        return new self(Uuid::uuid4());
    }

    public static function fromString(string $id): self
    {
        return new self(Uuid::fromString($id));
    }

    public function toString(): string
    {
        return $this->value->toString();
    }

    public function equals(object $other): bool
    {
        return $other instanceof self &&
               $this->value->equals($other->value);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
```

### Domain Events

Domain events capture important business occurrences:

```php
<?php

namespace Modules\Catalog\Domain\Events;

use Modules\Catalog\Domain\ValueObjects\ProductId;
use TaiCrm\LaravelModularDdd\Foundation\DomainEvent;

readonly class ProductCreated extends DomainEvent
{
    public function __construct(
        public ProductId $productId,
        public string $name,
        public Money $price,
        public \DateTimeImmutable $createdAt
    ) {
        parent::__construct();
    }

    public function getPayload(): array
    {
        return [
            'product_id' => $this->productId->toString(),
            'name' => $this->name,
            'price' => [
                'amount' => $this->price->getAmount(),
                'currency' => $this->price->getCurrency()
            ],
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
```

## CQRS Implementation

### Commands (Write Operations)

Commands represent intentions to change system state:

```php
<?php

namespace Modules\Catalog\Application\Commands\CreateProduct;

readonly class CreateProductCommand
{
    public function __construct(
        public string $name,
        public int $priceAmount,
        public string $currency,
        public ?string $description = null,
        public ?string $categoryId = null
    ) {}
}
```

### Command Handlers

Command handlers execute the business logic:

```php
<?php

namespace Modules\Catalog\Application\Commands\CreateProduct;

use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\ValueObjects\ProductId;
use Modules\Catalog\Domain\ValueObjects\Money;
use Modules\Catalog\Domain\Repositories\ProductRepositoryInterface;
use TaiCrm\LaravelModularDdd\Communication\EventBus;

readonly class CreateProductHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private EventBus $eventBus
    ) {}

    public function handle(CreateProductCommand $command): string
    {
        $productId = ProductId::generate();
        $price = Money::fromAmount($command->priceAmount, $command->currency);

        $product = Product::create(
            $productId,
            $command->name,
            $price
        );

        $this->productRepository->save($product);

        // Dispatch domain events
        $this->eventBus->dispatchMany($product->releaseEvents());

        return $productId->toString();
    }
}
```

### Queries (Read Operations)

Queries retrieve data without side effects:

```php
<?php

namespace Modules\Catalog\Application\Queries\GetProduct;

readonly class GetProductQuery
{
    public function __construct(
        public string $productId
    ) {}
}

class GetProductHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository
    ) {}

    public function handle(GetProductQuery $query): ?ProductDTO
    {
        $productId = ProductId::fromString($query->productId);
        $product = $this->productRepository->findById($productId);

        if (!$product) {
            return null;
        }

        return ProductDTO::fromAggregate($product);
    }
}
```

## Repository Pattern

### Repository Interface

Define contracts in the domain layer:

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
    public function findByStatus(ProductStatus $status): Collection;
    public function remove(ProductId $id): void;
    public function exists(ProductId $id): bool;
}
```

### Eloquent Implementation

Implement in the infrastructure layer:

```php
<?php

namespace Modules\Catalog\Infrastructure\Persistence\Eloquent\Repositories;

use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\ValueObjects\ProductId;
use Modules\Catalog\Domain\Repositories\ProductRepositoryInterface;
use Modules\Catalog\Infrastructure\Persistence\Eloquent\Models\ProductModel;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function save(Product $product): void
    {
        $model = ProductModel::firstOrNew([
            'id' => $product->getId()->toString()
        ]);

        $model->fill([
            'name' => $product->getName(),
            'price_amount' => $product->getPrice()->getAmount(),
            'price_currency' => $product->getPrice()->getCurrency(),
            'status' => $product->getStatus()->value,
        ]);

        $model->save();
    }

    public function findById(ProductId $id): ?Product
    {
        $model = ProductModel::find($id->toString());

        if (!$model) {
            return null;
        }

        return $this->modelToDomain($model);
    }

    private function modelToDomain(ProductModel $model): Product
    {
        return Product::reconstitute(
            ProductId::fromString($model->id),
            $model->name,
            Money::fromAmount($model->price_amount, $model->price_currency),
            ProductStatus::from($model->status)
        );
    }
}
```

## Inter-Module Communication

### Service Contracts

Define contracts for services other modules can use:

```php
<?php

namespace Modules\Catalog\Contracts;

use Modules\Catalog\DTOs\ProductDTO;

interface ProductServiceInterface
{
    public function findProduct(string $productId): ?ProductDTO;
    public function isProductAvailable(string $productId): bool;
    public function getProductPrice(string $productId): ?Money;
    public function searchProducts(array $criteria): Collection;
}
```

### Event-Based Communication

Use domain events for loose coupling:

```php
<?php

// Publisher (Catalog module)
class ProductPriceChanged extends DomainEvent
{
    public function __construct(
        public ProductId $productId,
        public Money $oldPrice,
        public Money $newPrice
    ) {
        parent::__construct();
    }
}

// Subscriber (Pricing module)
class UpdatePricingIndexHandler
{
    public function handle(ProductPriceChanged $event): void
    {
        $this->pricingIndex->updatePrice(
            $event->productId->toString(),
            $event->newPrice
        );
    }
}
```

## Component Generation

### Generate Domain Components

```bash
# Generate aggregate root
php artisan module:stub model Product Catalog

# Generate value object
php artisan module:stub value-object ProductId Catalog

# Generate domain event
php artisan module:stub event ProductCreated Catalog

# Generate domain service
php artisan module:stub service PricingService Catalog

# Generate repository interface
php artisan module:stub repository Product Catalog
```

### Generate Application Components

```bash
# Generate command and handler
php artisan module:stub command CreateProduct Catalog

# Generate query and handler
php artisan module:stub query GetProduct Catalog

# Generate application service
php artisan module:stub app-service ProductApplicationService Catalog
```

### Generate Infrastructure Components

```bash
# Generate Eloquent repository implementation
php artisan module:stub eloquent-repository Product Catalog

# Generate external service adapter
php artisan module:stub adapter PaymentGatewayAdapter Catalog

# Generate cache implementation
php artisan module:stub cache ProductCacheRepository Catalog
```

## Testing Strategy

### Unit Tests

Test domain logic in isolation:

```php
<?php

namespace Modules\Catalog\Tests\Unit;

use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\ValueObjects\ProductId;
use Modules\Catalog\Domain\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    public function test_can_create_product(): void
    {
        $id = ProductId::generate();
        $name = 'Test Product';
        $price = Money::fromAmount(1000, 'USD');

        $product = Product::create($id, $name, $price);

        $this->assertEquals($id, $product->getId());
        $this->assertEquals($name, $product->getName());
        $this->assertEquals($price, $product->getPrice());
        $this->assertTrue($product->hasUncommittedEvents());
    }

    public function test_can_change_price(): void
    {
        $product = $this->createProduct();
        $newPrice = Money::fromAmount(1500, 'USD');

        $product->changePrice($newPrice);

        $this->assertEquals($newPrice, $product->getPrice());
        $this->assertCount(2, $product->getUncommittedEvents()); // Created + PriceChanged
    }
}
```

### Integration Tests

Test components working together:

```php
<?php

namespace Modules\Catalog\Tests\Integration;

use Modules\Catalog\Application\Commands\CreateProduct\CreateProductCommand;
use Modules\Catalog\Application\Commands\CreateProduct\CreateProductHandler;
use Modules\Catalog\Tests\TestCase;

class CreateProductHandlerTest extends TestCase
{
    public function test_can_create_product(): void
    {
        $command = new CreateProductCommand(
            name: 'Test Product',
            priceAmount: 1000,
            currency: 'USD'
        );

        $handler = $this->app->make(CreateProductHandler::class);
        $productId = $handler->handle($command);

        $this->assertNotEmpty($productId);
        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'name' => 'Test Product'
        ]);
    }
}
```

## Development Workflow

### Watch Mode

Start file watching for automatic reloading:

```bash
php artisan module:dev watch
```

### Development Links

Create symlinks for assets and views:

```bash
php artisan module:dev link Catalog
```

### Health Monitoring

Check module health during development:

```bash
php artisan module:health Catalog --verbose
```

## Best Practices

### Domain Layer
- Keep domain logic pure - no dependencies on infrastructure
- Use value objects for concepts that don't have identity
- Aggregate roots should protect business invariants
- Domain events should capture business occurrences

### Application Layer
- Commands should be simple data holders
- Handlers should coordinate domain operations
- Use DTOs for data transfer between layers
- Keep application services thin

### Infrastructure Layer
- Implement domain contracts
- Handle technical concerns (database, caching, etc.)
- Use adapters for external services
- Keep implementations swappable

### Testing
- Test domain logic extensively with unit tests
- Use integration tests for complex workflows
- Mock external dependencies
- Test both happy path and edge cases

This development guide provides a solid foundation for building robust, maintainable modules using DDD principles.