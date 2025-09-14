# CQRS Command/Query Bus System

This module provides a comprehensive CQRS (Command Query Responsibility Segregation) implementation for the Laravel Modular DDD package.

## Features

- ✅ Command Bus with validation and error handling
- ✅ Query Bus with automatic caching support
- ✅ Automatic handler registration for modules
- ✅ UUID-based command/query tracking
- ✅ Validation integration with Laravel's validator
- ✅ Facade support for easy access
- ✅ Helper functions for quick usage
- ✅ Artisan commands for code generation

## Core Components

### Base Classes

#### Command
```php
use TaiCrm\LaravelModularDdd\Foundation\Command;

class CreateUserCommand extends Command
{
    private string $email;
    private string $name;

    public function __construct(string $email, string $name)
    {
        $this->email = $email;
        $this->name = $name;
        parent::__construct();
    }

    public function getValidationRules(): array
    {
        return [
            'email' => 'required|email|unique:users',
            'name' => 'required|string|max:255',
        ];
    }

    protected function toArray(): array
    {
        return [
            'email' => $this->email,
            'name' => $this->name,
        ];
    }
}
```

#### Query
```php
use TaiCrm\LaravelModularDdd\Foundation\Query;

class GetUserQuery extends Query
{
    private string $userId;

    public function __construct(string $userId)
    {
        $this->userId = $userId;
        parent::__construct();
    }

    protected function isCacheable(): bool
    {
        return true;
    }

    protected function getDefaultCacheTtl(): int
    {
        return 600; // 10 minutes
    }

    protected function toArray(): array
    {
        return ['user_id' => $this->userId];
    }
}
```

### Handlers

#### Command Handler
```php
use TaiCrm\LaravelModularDdd\Foundation\Contracts\CommandHandlerInterface;

class CreateUserCommandHandler implements CommandHandlerInterface
{
    public function handle(CreateUserCommand $command): mixed
    {
        // Create user logic here
        $user = User::create($command->toArray());

        return $user;
    }
}
```

#### Query Handler
```php
use TaiCrm\LaravelModularDdd\Foundation\Contracts\QueryHandlerInterface;

class GetUserQueryHandler implements QueryHandlerInterface
{
    public function handle(GetUserQuery $query): mixed
    {
        return User::find($query->toArray()['user_id']);
    }
}
```

## Usage

### Using Facades
```php
use TaiCrm\LaravelModularDdd\Foundation\Facades\CommandBus;
use TaiCrm\LaravelModularDdd\Foundation\Facades\QueryBus;

// Dispatch a command
$result = CommandBus::dispatch(new CreateUserCommand('john@example.com', 'John Doe'));

// Ask a query
$user = QueryBus::ask(new GetUserQuery('user-id-123'));
```

### Using Helper Functions
```php
// Dispatch a command
$result = dispatch_command(new CreateUserCommand('john@example.com', 'John Doe'));

// Ask a query
$user = ask_query(new GetUserQuery('user-id-123'));

// Register handlers manually
register_command_handler(CreateUserCommand::class, CreateUserCommandHandler::class);
register_query_handler(GetUserQuery::class, GetUserQueryHandler::class);
```

### Using Dependency Injection
```php
use TaiCrm\LaravelModularDdd\Foundation\CommandBus;
use TaiCrm\LaravelModularDdd\Foundation\QueryBus;

class UserController extends Controller
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus
    ) {}

    public function store(Request $request)
    {
        $command = new CreateUserCommand(
            $request->email,
            $request->name
        );

        $user = $this->commandBus->dispatch($command);

        return response()->json($user);
    }

    public function show(string $id)
    {
        $query = new GetUserQuery($id);
        $user = $this->queryBus->ask($query);

        return response()->json($user);
    }
}
```

## Artisan Commands

### Generate CQRS Command
```bash
# Basic command
php artisan module:make-command UserModule CreateUser

# With aggregate and validation
php artisan module:make-command UserModule CreateUser --aggregate=User --validation
```

### Generate CQRS Query
```bash
# Basic query
php artisan module:make-query UserModule GetUser

# With aggregate and caching
php artisan module:make-query UserModule GetUser --aggregate=User --cacheable
```

## Automatic Handler Registration

The system automatically discovers and registers handlers for modules following this structure:

```
modules/
├── UserModule/
│   ├── Application/
│   │   ├── Commands/
│   │   │   └── CreateUserCommand.php
│   │   ├── Queries/
│   │   │   └── GetUserQuery.php
│   │   └── Handlers/
│   │       ├── Commands/
│   │       │   └── CreateUserCommandHandler.php
│   │       └── Queries/
│   │           └── GetUserQueryHandler.php
```

## Error Handling

### Command Validation
Commands are automatically validated on construction. Invalid commands will throw validation exceptions:

```php
try {
    $command = new CreateUserCommand('invalid-email', '');
    // This will throw validation exception
} catch (InvalidArgumentException $e) {
    // Handle validation errors
    $errors = json_decode($e->getMessage(), true);
}
```

### Missing Handlers
If no handler is registered for a command/query, an exception will be thrown:

```php
try {
    $result = CommandBus::dispatch(new UnregisteredCommand());
} catch (InvalidArgumentException $e) {
    // "No handler registered for command: UnregisteredCommand"
}
```

## Caching

Queries can implement caching by overriding methods:

```php
class CacheableQuery extends Query
{
    protected function isCacheable(): bool
    {
        return true;
    }

    protected function getDefaultCacheTtl(): int
    {
        return 3600; // 1 hour
    }
}
```

The QueryBus will automatically cache results and serve from cache on subsequent requests.

## Tracing and Monitoring

Every command and query gets a unique UUID for tracking:

```php
$command = new CreateUserCommand('john@example.com', 'John Doe');
$commandId = $command->getCommandId(); // UUID v4
$commandType = $command->getCommandType(); // 'CreateUserCommand'
$payload = $command->getPayload(); // Full payload with metadata
```

This enables comprehensive logging and monitoring of all CQRS operations.