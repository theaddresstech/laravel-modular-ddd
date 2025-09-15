# Inter-Module Communication Guide

This guide covers how modules communicate with each other in Laravel Modular DDD, focusing on event-driven architecture, service contracts, and dependency management.

## Communication Patterns

### 1. Event-Driven Communication (Recommended)

Event-driven communication is the primary method for modules to interact while maintaining loose coupling.

#### Publishing Domain Events

```php
// In your domain entity or aggregate root
use TaiCrm\LaravelModularDdd\Foundation\DomainEvent;

class UserRegistered extends DomainEvent
{
    public function __construct(
        public readonly string $userId,
        public readonly string $email,
        public readonly string $name,
        public readonly array $metadata = []
    ) {
        parent::__construct();
    }

    public function getEventName(): string
    {
        return 'user.registered';
    }

    public function getPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'email' => $this->email,
            'name' => $this->name,
            'metadata' => $this->metadata,
        ];
    }
}

// In your aggregate root or service
use TaiCrm\LaravelModularDdd\Foundation\Facades\EventBus;

class User extends AggregateRoot
{
    public function register(string $email, string $name): void
    {
        // ... business logic

        // Publish domain event
        $this->recordEvent(new UserRegistered(
            $this->id,
            $email,
            $name,
            ['registered_at' => now()]
        ));
    }

    // Or manually publish
    public function publishRegistrationEvent(): void
    {
        EventBus::publish(new UserRegistered($this->id, $this->email, $this->name));
    }
}
```

#### Listening to Events in Other Modules

```php
// In FranchiseModule - listening to UserModule events
use TaiCrm\LaravelModularDdd\Foundation\Contracts\DomainEventInterface;

class CreateFranchiseProfile
{
    public function handle(DomainEventInterface $event): void
    {
        if ($event->getEventName() === 'user.registered') {
            $payload = $event->getPayload();

            // Create franchise profile for new user
            $this->createFranchiseProfile(
                $payload['user_id'],
                $payload['email'],
                $payload['name']
            );
        }
    }

    private function createFranchiseProfile(string $userId, string $email, string $name): void
    {
        // Business logic to create franchise profile
        FranchiseProfile::create([
            'user_id' => $userId,
            'email' => $email,
            'name' => $name,
            'status' => 'pending_verification',
        ]);
    }
}

// Register the listener in FranchiseModuleServiceProvider
use TaiCrm\LaravelModularDdd\Foundation\Facades\EventBus;

class FranchiseModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        EventBus::listen('user.registered', CreateFranchiseProfile::class);

        // Or listen to all events from a module
        EventBus::listenToModule('UserModule', [
            CreateFranchiseProfile::class,
            NotifyFranchiseTeam::class,
        ]);
    }
}
```

### 2. Service Contract Communication

Use service contracts for direct, synchronous communication between modules.

#### Define Service Contracts

```php
// In HrModule - define contract
namespace Modules\Hr\Contracts;

interface EmployeeServiceInterface
{
    public function getEmployee(string $employeeId): ?array;
    public function isActive(string $employeeId): bool;
    public function getEmployeesByDepartment(string $department): array;
    public function createEmployee(array $data): string;
}
```

#### Implement Service Contract

```php
// In HrModule - implement contract
namespace Modules\Hr\Services;

use Modules\Hr\Contracts\EmployeeServiceInterface;

class EmployeeService implements EmployeeServiceInterface
{
    public function getEmployee(string $employeeId): ?array
    {
        $employee = Employee::find($employeeId);

        return $employee ? [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'department' => $employee->department,
            'position' => $employee->position,
            'is_active' => $employee->is_active,
        ] : null;
    }

    public function isActive(string $employeeId): bool
    {
        return Employee::where('id', $employeeId)
            ->where('is_active', true)
            ->exists();
    }

    public function getEmployeesByDepartment(string $department): array
    {
        return Employee::where('department', $department)
            ->where('is_active', true)
            ->get()
            ->map(fn($emp) => [
                'id' => $emp->id,
                'name' => $emp->name,
                'email' => $emp->email,
                'position' => $emp->position,
            ])
            ->toArray();
    }

    public function createEmployee(array $data): string
    {
        $employee = Employee::create($data);
        return $employee->id;
    }
}
```

#### Register Service Contract

```php
// In HrModuleServiceProvider
use TaiCrm\LaravelModularDdd\Communication\Facades\ServiceRegistry;

class HrModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EmployeeServiceInterface::class, EmployeeService::class);
    }

    public function boot(): void
    {
        // Register service with the global service registry
        ServiceRegistry::register('hr.employee', EmployeeServiceInterface::class);
        ServiceRegistry::registerModule('HrModule', [
            'employee' => EmployeeServiceInterface::class,
            'department' => DepartmentServiceInterface::class,
        ]);
    }
}
```

#### Consume Service Contract

```php
// In FranchiseModule - consume Hr services
namespace Modules\Franchise\Services;

use Modules\Hr\Contracts\EmployeeServiceInterface;
use TaiCrm\LaravelModularDdd\Communication\Facades\ServiceRegistry;

class FranchiseService
{
    public function __construct(
        private EmployeeServiceInterface $employeeService
    ) {}

    public function createFranchise(array $data): Franchise
    {
        // Validate that the manager is an active employee
        if (!$this->employeeService->isActive($data['manager_id'])) {
            throw new InvalidArgumentException('Manager must be an active employee');
        }

        $manager = $this->employeeService->getEmployee($data['manager_id']);

        return Franchise::create([
            'name' => $data['name'],
            'manager_id' => $data['manager_id'],
            'manager_name' => $manager['name'],
            'manager_email' => $manager['email'],
            'location' => $data['location'],
        ]);
    }

    // Alternative: Use service registry for dynamic discovery
    public function getManagerInfo(string $managerId): ?array
    {
        $employeeService = ServiceRegistry::resolve('hr.employee');
        return $employeeService->getEmployee($managerId);
    }
}
```

### 3. Query-Based Communication

Use CQRS queries for read-only data access across modules.

#### Cross-Module Queries

```php
// In FranchiseModule - query Hr data
use TaiCrm\LaravelModularDdd\Foundation\Facades\QueryBus;

class GetFranchiseWithManagerQuery extends Query
{
    public function __construct(
        private string $franchiseId
    ) {
        parent::__construct();
    }

    protected function toArray(): array
    {
        return ['franchise_id' => $this->franchiseId];
    }
}

class GetFranchiseWithManagerHandler implements QueryHandlerInterface
{
    public function handle(QueryInterface $query): array
    {
        $franchise = Franchise::find($query->franchiseId);

        if (!$franchise) {
            throw new ModelNotFoundException('Franchise not found');
        }

        // Query Hr module for manager details
        $managerQuery = new GetEmployeeQuery($franchise->manager_id);
        $manager = QueryBus::ask($managerQuery);

        return [
            'franchise' => $franchise->toArray(),
            'manager' => $manager,
        ];
    }
}
```

## Dependency Management

### Module Dependencies

Define module dependencies in the manifest file:

```json
// modules/Franchise/manifest.json
{
    "name": "FranchiseModule",
    "version": "1.0.0",
    "description": "Franchise management system",
    "dependencies": {
        "HrModule": "^1.0.0",
        "UserModule": "^2.0.0"
    },
    "services": {
        "franchise.management": "Modules\\Franchise\\Services\\FranchiseService",
        "franchise.reporting": "Modules\\Franchise\\Services\\ReportingService"
    },
    "events": {
        "publishes": [
            "franchise.created",
            "franchise.updated",
            "franchise.deleted"
        ],
        "listens": [
            "user.registered",
            "hr.employee.created",
            "hr.employee.updated"
        ]
    }
}
```

### Graceful Degradation

Handle missing dependencies gracefully:

```php
use TaiCrm\LaravelModularDdd\Communication\Facades\ServiceRegistry;

class FranchiseService
{
    public function createFranchise(array $data): Franchise
    {
        // Check if Hr module is available
        if (ServiceRegistry::isAvailable('hr.employee')) {
            $this->validateManager($data['manager_id']);
        } else {
            // Log warning and continue without validation
            Log::warning('Hr module not available, skipping manager validation');
        }

        return Franchise::create($data);
    }

    private function validateManager(string $managerId): void
    {
        try {
            $employeeService = ServiceRegistry::resolve('hr.employee');

            if (!$employeeService->isActive($managerId)) {
                throw new InvalidArgumentException('Manager must be an active employee');
            }
        } catch (ServiceNotFoundException $e) {
            Log::warning('Employee service not available', ['manager_id' => $managerId]);
            // Continue without validation or throw based on business rules
        }
    }
}
```

## Event Bus Configuration

### Synchronous vs Asynchronous Processing

```php
// config/modular-ddd.php
'event_bus' => [
    'driver' => env('MODULAR_DDD_EVENT_DRIVER', 'sync'), // 'sync' or 'async'
    'async_queue' => env('MODULAR_DDD_EVENT_QUEUE', 'default'),

    // Event routing configuration
    'routing' => [
        'auto_discover_listeners' => true,
        'listener_discovery_paths' => [
            'Listeners',
            'EventHandlers',
        ],
    ],

    // Performance settings
    'batch_size' => 100,
    'retry_attempts' => 3,
    'retry_delay' => 60, // seconds
],
```

### Event Filtering and Routing

```php
// In a service provider
use TaiCrm\LaravelModularDdd\Foundation\Facades\EventBus;

class FranchiseModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Listen to specific events
        EventBus::listen('user.registered', CreateFranchiseProfile::class);
        EventBus::listen('hr.employee.updated', UpdateFranchiseManager::class);

        // Listen to all events with filtering
        EventBus::listenAll(function (DomainEventInterface $event) {
            if (str_starts_with($event->getEventName(), 'hr.')) {
                // Handle all Hr events
                $this->handleHrEvent($event);
            }
        });

        // Conditional listeners
        EventBus::listenWhen('user.deleted', DeleteFranchiseProfile::class, function ($event) {
            // Only process if user had franchise profile
            return FranchiseProfile::where('user_id', $event->getPayload()['user_id'])->exists();
        });
    }
}
```

## Testing Inter-Module Communication

### Integration Tests

```php
use Tests\TestCase;
use Modules\Hr\Models\Employee;
use Modules\Franchise\Models\Franchise;

class FranchiseHrIntegrationTest extends TestCase
{
    /** @test */
    public function it_creates_franchise_with_valid_manager()
    {
        // Arrange: Create an employee in Hr module
        $employee = Employee::factory()->create([
            'name' => 'John Manager',
            'email' => 'john@example.com',
            'department' => 'Operations',
            'is_active' => true,
        ]);

        // Act: Create franchise with this manager
        $franchiseData = [
            'name' => 'Downtown Franchise',
            'manager_id' => $employee->id,
            'location' => 'Downtown District',
        ];

        $response = $this->postJson('/api/franchises', $franchiseData);

        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('franchises', [
            'name' => 'Downtown Franchise',
            'manager_id' => $employee->id,
            'manager_name' => 'John Manager',
        ]);
    }

    /** @test */
    public function it_handles_hr_module_unavailable_gracefully()
    {
        // Simulate Hr module being disabled
        $this->app->forgetInstance(EmployeeServiceInterface::class);

        $franchiseData = [
            'name' => 'Test Franchise',
            'manager_id' => 'invalid-id',
            'location' => 'Test Location',
        ];

        $response = $this->postJson('/api/franchises', $franchiseData);

        // Should still create franchise but log warning
        $response->assertStatus(201);
        $this->assertLogged('warning', 'Hr module not available');
    }

    /** @test */
    public function it_processes_employee_update_events()
    {
        // Arrange
        $employee = Employee::factory()->create(['name' => 'Old Name']);
        $franchise = Franchise::factory()->create([
            'manager_id' => $employee->id,
            'manager_name' => 'Old Name',
        ]);

        // Act: Update employee
        $employee->update(['name' => 'New Name']);

        // The event should trigger franchise update
        $this->artisan('queue:work --once'); // Process queued events

        // Assert
        $franchise->refresh();
        $this->assertEquals('New Name', $franchise->manager_name);
    }
}
```

### Event Testing

```php
use TaiCrm\LaravelModularDdd\Foundation\Facades\EventBus;

class EventCommunicationTest extends TestCase
{
    /** @test */
    public function it_publishes_user_registered_event()
    {
        EventBus::fake();

        // Create user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Assert event was published
        EventBus::assertPublished('user.registered', function ($event) use ($user) {
            return $event->getPayload()['user_id'] === $user->id;
        });
    }

    /** @test */
    public function it_handles_cross_module_event_processing()
    {
        // Create employee
        $employee = Employee::factory()->create();

        // Publish employee created event
        EventBus::publish(new EmployeeCreated($employee->id, $employee->name, $employee->email));

        // Process events
        $this->artisan('queue:work --once');

        // Assert that other modules responded
        $this->assertDatabaseHas('franchise_notifications', [
            'type' => 'employee_created',
            'employee_id' => $employee->id,
        ]);
    }
}
```

## Best Practices

### 1. Event Design

- **Immutable Events**: Events should be immutable once created
- **Rich Events**: Include enough data to avoid additional queries
- **Versioned Events**: Version events for backward compatibility
- **Idempotent Handlers**: Ensure event handlers can be safely replayed

### 2. Service Contracts

- **Stable Interfaces**: Keep interfaces stable and use versioning for changes
- **Error Handling**: Handle service unavailability gracefully
- **Timeout Handling**: Set appropriate timeouts for service calls
- **Circuit Breaker**: Implement circuit breaker pattern for resilience

### 3. Dependency Management

- **Loose Coupling**: Minimize direct dependencies between modules
- **Graceful Degradation**: Handle missing dependencies appropriately
- **Feature Flags**: Use feature flags to control inter-module interactions
- **Health Checks**: Monitor inter-module communication health

### 4. Performance Considerations

- **Async Processing**: Use async event processing for non-critical operations
- **Batching**: Batch events and service calls where possible
- **Caching**: Cache frequently accessed cross-module data
- **Monitoring**: Monitor cross-module communication performance

---

This guide provides comprehensive patterns for inter-module communication. Choose the appropriate pattern based on your specific use case: events for loose coupling, service contracts for direct access, and queries for read operations.