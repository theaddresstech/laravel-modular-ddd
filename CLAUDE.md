# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a comprehensive Laravel package (`mghrby/laravel-modular-ddd`) implementing modular Domain-Driven Design (DDD) architecture with enterprise-grade features including CQRS, API versioning, performance monitoring, and authorization systems.

## Key Development Commands

### Testing Commands
```bash
# Run all tests
composer test
# or
vendor/bin/phpunit

# Run specific test suites
composer test-unit          # Unit tests only
composer test-integration   # Integration tests only
composer test-feature       # Feature tests only

# Run tests with coverage
composer test-coverage      # Generates HTML coverage report

# Run single test file
vendor/bin/phpunit tests/Unit/SomeTest.php

# Run single test method
vendor/bin/phpunit --filter testMethodName
```

### Code Quality Commands
```bash
# Check code style (dry run)
composer style
# or
vendor/bin/php-cs-fixer fix --dry-run --diff

# Fix code style automatically
composer style:fix
# or
vendor/bin/php-cs-fixer fix

# Run static analysis
composer analyse
# or
vendor/bin/psalm

# Run security audit
composer security
# or
composer audit

# Run all pre-release checks
composer pre-release
```

### Package Development Commands
```bash
# Validate package structure and integrity
composer validate-package
# or
php scripts/validate-package.php

# Build and test package for release
composer pre-release
```

### Module Development Commands (when testing the package)
```bash
# Create a complete module with DDD structure
php artisan module:make {ModuleName} --aggregate={AggregateName}

# Generate complete REST API
php artisan module:make-api {Module} {Resource} --auth --validation --swagger

# Run module-specific commands
php artisan module:list
php artisan module:health {ModuleName}
php artisan module:performance:analyze
```

## High-Level Architecture

### Core Package Structure
- **`src/`**: Main package source code organized by feature domains
- **`stubs/`**: Code generation templates for module scaffolding
- **`tests/`**: Comprehensive test suite (Unit, Integration, Feature)
- **`examples/`**: Working examples showing DDD implementation patterns
- **`config/`**: Package configuration with extensive customization options

### Domain-Driven Design Layers
The package implements a 4-layer DDD architecture:

1. **Domain Layer** (`src/Foundation/`): Pure business logic
   - `AggregateRoot`: Base class for domain aggregates with event sourcing
   - `Entity`, `ValueObject`: DDD building blocks
   - `DomainEvent`: Event-driven communication foundation

2. **Application Layer** (`src/Foundation/`): Use case orchestration
   - `CommandBus`, `QueryBus`: CQRS implementation
   - Command/Query handlers with auto-discovery
   - Application services coordination

3. **Infrastructure Layer** (`src/`): External system integration
   - `ModuleManager/`: Module lifecycle management
   - `Monitoring/`: Performance and health monitoring
   - `Security/`: Security scanning and validation

4. **Presentation Layer**: Generated via module scaffolding
   - HTTP controllers with API versioning
   - Request validation and resource transformations
   - Middleware for authentication and authorization

### CQRS Implementation
- **Command Pattern**: Write operations with validation (`src/Foundation/CommandBus.php`)
- **Query Pattern**: Read operations with caching (`src/Foundation/QueryBus.php`)
- **Handler Discovery**: Automatic registration of command/query handlers
- **Event Sourcing**: Domain events with aggregate root pattern

### API Versioning System
- **Multi-Strategy Negotiation**: URL, header, query parameter, content negotiation
- **Backward Compatibility**: Automatic request/response transformation
- **Version-Aware Routing**: Dynamic route registration by version
- **Documentation Integration**: Version-specific Swagger generation

### Module System Architecture
- **Dynamic Discovery**: Auto-discovery of modules with dependency resolution
- **Service Registration**: Contract-based inter-module communication
- **Event Bus**: Asynchronous communication via domain events
- **Health Monitoring**: Comprehensive module health checks and diagnostics

### Performance Monitoring
- **Query Analysis**: N+1 detection and slow query identification
- **Cache Monitoring**: Hit/miss rate tracking with optimization suggestions
- **Resource Tracking**: Memory, CPU, and execution time monitoring
- **Real-time Analysis**: Live performance monitoring with threshold alerts

## Key Configuration Files

### Package Configuration
- **`config/modular-ddd.php`**: Main package configuration with API versioning, monitoring, security settings
- **`phpunit.xml`**: Test suite configuration with multiple test types and coverage reporting
- **`.php-cs-fixer.php`**: Comprehensive code style rules following PSR-12 with Laravel conventions

### Development Workflow
- **Code Style**: Automatically enforced via PHP CS Fixer with strict PSR-12 compliance
- **Static Analysis**: Psalm configuration for type safety and code quality
- **Testing Strategy**: Multi-layered testing (Unit, Integration, Feature) with high coverage requirements
- **Security**: Built-in security scanning and vulnerability detection

## Module Generation Patterns

When developing modules using this package:

1. **Start with aggregate definition**: `module:make ModuleName --aggregate=EntityName`
2. **Generate complete API**: `module:make-api` with authentication, validation, and documentation
3. **Follow DDD patterns**: Use provided base classes (AggregateRoot, Entity, ValueObject)
4. **Implement CQRS**: Use CommandBus/QueryBus for all business operations
5. **Test comprehensively**: Generate and use provided test templates
6. **Monitor performance**: Leverage built-in performance analysis tools

## Testing Strategy

- **Unit Tests**: Domain logic in isolation (`tests/Unit/`)
- **Integration Tests**: Component interaction (`tests/Integration/`)
- **Feature Tests**: End-to-end functionality (`tests/Feature/`)
- **Example Tests**: Working module examples (`examples/*/Tests/`)

The test configuration supports parallel execution, coverage reporting, and strict quality enforcement.

## Security Considerations

- **Module Scanning**: Automated security vulnerability detection
- **Permission System**: Fine-grained authorization with module-specific permissions
- **Input Validation**: Multi-layer validation (request, command, domain)
- **Dependency Management**: Secure module dependency resolution with version constraints