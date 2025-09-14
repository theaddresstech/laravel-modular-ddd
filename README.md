# Laravel Modular DDD

A comprehensive Laravel package for implementing modular Domain-Driven Design architecture with dynamic module management, inspired by Odoo's module system.

## Features

- **Complete Modular Architecture**: Vertical slice modules with Domain, Application, Infrastructure, and Presentation layers
- **Dynamic Module Management**: Install, enable, disable, and remove modules without affecting other parts of the system
- **Dependency Resolution**: Automatic dependency management with version constraints and conflict detection
- **Event-Driven Communication**: Inter-module communication through domain events and contracts
- **DDD Foundation Classes**: Base classes for aggregates, entities, value objects, and domain services
- **Comprehensive Tooling**: Full suite of Artisan commands for module lifecycle management
- **Testing Infrastructure**: Built-in testing utilities for module isolation and cross-module integration

## Installation

```bash
composer require tai-crm/laravel-modular-ddd
```

Publish the configuration:

```bash
php artisan vendor:publish --provider="TaiCrm\LaravelModularDdd\Providers\ModularDddServiceProvider"
```

## Quick Start

### Create Your First Module

```bash
# Generate a new module
php artisan module:make Catalog --aggregate=Product

# Install and enable the module
php artisan module:install Catalog
php artisan module:enable Catalog
```

### Module Structure

```
modules/Catalog/
├── manifest.json          # Module configuration and dependencies
├── Domain/               # Business logic - entities, value objects, events
├── Application/          # Use cases - commands, queries, handlers
├── Infrastructure/       # External interfaces - repositories, APIs
├── Presentation/         # Controllers, requests, resources
├── Database/            # Migrations, seeders, factories
├── Routes/              # API and web routes
└── Tests/               # Unit and integration tests
```

## Available Commands

```bash
# Module Management
php artisan module:list              # List all modules
php artisan module:install {name}    # Install a module
php artisan module:enable {name}     # Enable a module
php artisan module:disable {name}    # Disable a module
php artisan module:remove {name}     # Remove a module
php artisan module:status {name}     # Show detailed module status

# Development
php artisan module:make {name}       # Create new module with full DDD structure
php artisan module:health {name}     # Check module health
php artisan module:health --all      # Check all modules health

# Database Operations
php artisan module:migrate {name}    # Run module migrations
php artisan module:migrate --all     # Run migrations for all enabled modules
php artisan module:seed {name}       # Run module seeders
php artisan module:seed --all        # Run seeders for all enabled modules

# Cache Management
php artisan module:cache clear       # Clear module cache
php artisan module:cache rebuild     # Rebuild module cache

# Module Updates & Maintenance
php artisan module:update {name}     # Update a module to newer version
php artisan module:update --all      # Update all modules
php artisan module:backup {name}     # Create module backup
php artisan module:restore {backup}  # Restore from backup

# Development Tools
php artisan module:dev watch         # Watch modules for file changes
php artisan module:dev link {module} # Create development symlinks
php artisan module:stub model Product Catalog  # Generate DDD components
```

## Architecture Principles

### Modular Independence
Each module is a complete vertical slice containing all architectural layers, with clear boundaries and communication patterns.

### Inter-Module Communication
- **Contract-Based**: Modules expose interfaces, others depend on contracts
- **Event-Driven**: Modules communicate through domain events
- **Service Registry**: Runtime service discovery with fallback mechanisms

### Dependency Management
- Modules declare dependencies in `manifest.json`
- Automatic dependency resolution with version constraints
- Graceful handling of missing optional dependencies

## Documentation

- [Getting Started Guide](docs/getting-started.md)
- [Architecture Overview](docs/architecture.md)
- [Module Development](docs/module-development.md)
- [API Reference](docs/api-reference.md)

## License

MIT License. See [LICENSE](LICENSE) for details.