# Contributing to Laravel Modular DDD

Thank you for your interest in contributing to Laravel Modular DDD! This document outlines the process and guidelines for contributing to this project.

## Table of Contents

1. [Code of Conduct](#code-of-conduct)
2. [Getting Started](#getting-started)
3. [Development Setup](#development-setup)
4. [Contributing Guidelines](#contributing-guidelines)
5. [Pull Request Process](#pull-request-process)
6. [Issue Reporting](#issue-reporting)
7. [Documentation](#documentation)
8. [Testing](#testing)
9. [Security](#security)

## Code of Conduct

This project adheres to a [Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code. Please report unacceptable behavior to support@tai-crm.com.

## Getting Started

### Ways to Contribute

- **Bug Reports**: Help us improve by reporting bugs
- **Feature Requests**: Suggest new features or improvements
- **Code Contributions**: Fix bugs, add features, improve performance
- **Documentation**: Improve or add documentation
- **Testing**: Add or improve tests
- **Examples**: Create example modules or use cases
- **Community Support**: Help others in discussions and issues

### Before Contributing

1. **Check existing issues** to avoid duplicates
2. **Discuss major changes** in an issue before starting work
3. **Read the architecture documentation** to understand the codebase
4. **Review the roadmap** to align with project direction

## Development Setup

### Prerequisites

- PHP 8.2 or higher
- Laravel 11.0 or higher
- Composer 2.4+
- Git
- A GitHub account

### Setup Instructions

1. **Fork the repository**
   ```bash
   # Fork on GitHub, then clone your fork
   git clone https://github.com/theaddresstech/laravel-modular-ddd.git
   cd laravel-modular-ddd
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Set up testing environment**
   ```bash
   cp .env.example .env.testing
   # Configure testing database settings
   ```

4. **Run tests to ensure everything works**
   ```bash
   composer test
   ```

5. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

### Development Workflow

1. **Make your changes** following our coding standards
2. **Add or update tests** for your changes
3. **Update documentation** as needed
4. **Run the test suite** to ensure nothing breaks
5. **Run static analysis** to catch potential issues
6. **Commit your changes** with descriptive commit messages

## Contributing Guidelines

### Coding Standards

We follow PSR-12 coding standards with additional project-specific rules:

#### PHP Code Style

- **PSR-12 Compliance**: All code must follow PSR-12 standards
- **Type Declarations**: Use strict types (`declare(strict_types=1);`)
- **Return Types**: Always declare return types
- **Property Types**: Use typed properties where possible
- **Nullable Types**: Use nullable types (`?string`) instead of union types with null

```php
<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Example;

class ExampleClass
{
    public function __construct(
        private readonly string $name,
        private readonly ?int $age = null
    ) {}

    public function getName(): string
    {
        return $this->name;
    }
}
```

#### Architecture Guidelines

- **Domain-Driven Design**: Follow DDD principles and patterns
- **SOLID Principles**: Adhere to SOLID design principles
- **Interface Segregation**: Use interfaces for all contracts
- **Dependency Injection**: Use constructor injection
- **Immutability**: Prefer immutable objects where possible

#### Code Organization

- **Namespace Structure**: Follow the established namespace conventions
- **File Placement**: Place files in appropriate directories based on their responsibility
- **Naming Conventions**: Use descriptive and meaningful names
- **Comments**: Write clear, concise comments for complex logic

### Commit Message Format

We use conventional commits for consistency:

```
type(scope): short description

Longer description if needed

Closes #issue-number
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

**Examples:**
```
feat(modules): add module signature verification

Add digital signature verification for module authenticity.
Includes signature generation, validation, and storage.

Closes #123
```

```
fix(dependency): resolve circular dependency detection

Fix infinite loop in dependency resolution when circular
dependencies are present in the module graph.

Closes #456
```

## Pull Request Process

### Before Submitting

1. **Ensure your code passes all tests**
   ```bash
   composer test
   ```

2. **Run static analysis**
   ```bash
   composer analyse
   ```

3. **Check code style**
   ```bash
   composer style:check
   ```

4. **Update documentation** if your changes affect user-facing functionality

5. **Add or update tests** to cover your changes

### Submitting a Pull Request

1. **Push your branch** to your fork
2. **Create a pull request** against the `main` branch
3. **Fill out the PR template** completely
4. **Link related issues** using keywords (fixes #123)
5. **Request review** from maintainers

### PR Requirements

- [ ] All tests pass
- [ ] Code follows project standards
- [ ] Documentation is updated
- [ ] Commit messages follow conventions
- [ ] No merge conflicts
- [ ] Appropriate labels are applied

### Review Process

1. **Automated checks** must pass (CI/CD)
2. **Code review** by at least one maintainer
3. **Testing** of new functionality
4. **Documentation review** for user-facing changes
5. **Final approval** and merge

## Issue Reporting

### Bug Reports

When reporting bugs, please include:

- **Clear description** of the issue
- **Steps to reproduce** the problem
- **Expected vs actual behavior**
- **Environment details** (PHP version, Laravel version, etc.)
- **Code samples** or error messages
- **Module configurations** if relevant

Use the bug report template:

```markdown
**Describe the bug**
A clear description of what the bug is.

**To Reproduce**
Steps to reproduce the behavior:
1. Go to '...'
2. Click on '...'
3. See error

**Expected behavior**
What you expected to happen.

**Environment:**
- PHP Version: [e.g., 8.2.1]
- Laravel Version: [e.g., 11.0]
- Package Version: [e.g., 1.0.0]
- OS: [e.g., Ubuntu 22.04]

**Additional context**
Any other context about the problem.
```

### Feature Requests

For feature requests, please include:

- **Clear description** of the feature
- **Use case** or problem it solves
- **Proposed implementation** (if you have ideas)
- **Alternatives considered**
- **Additional context**

## Documentation

### Types of Documentation

1. **API Documentation**: PHPDoc comments for all public methods
2. **User Guides**: Step-by-step instructions for users
3. **Architecture Docs**: Technical documentation for developers
4. **Examples**: Code examples and tutorials

### Documentation Guidelines

- **Clear and Concise**: Write in simple, clear language
- **Code Examples**: Include relevant code examples
- **Up-to-date**: Keep documentation current with code changes
- **Consistent Format**: Follow existing documentation patterns

### Building Documentation Locally

```bash
# Install documentation dependencies
npm install

# Build documentation
npm run docs:build

# Serve documentation locally
npm run docs:serve
```

## Testing

### Test Types

1. **Unit Tests**: Test individual components in isolation
2. **Integration Tests**: Test component interactions
3. **Feature Tests**: Test complete user workflows
4. **Performance Tests**: Test performance characteristics

### Writing Tests

- **Descriptive Names**: Use clear, descriptive test method names
- **Arrange-Act-Assert**: Follow the AAA pattern
- **Test Coverage**: Aim for high test coverage (>80%)
- **Mock External Dependencies**: Use mocks for external services

```php
public function test_can_install_module_with_dependencies(): void
{
    // Arrange
    $moduleManager = $this->createModuleManager();
    $module = $this->createModuleWithDependencies();

    // Act
    $result = $moduleManager->install($module->name);

    // Assert
    $this->assertTrue($result);
    $this->assertTrue($moduleManager->isInstalled($module->name));
}
```

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suite
composer test:unit
composer test:integration
composer test:feature

# Run with coverage
composer test:coverage

# Run specific test file
vendor/bin/phpunit tests/Unit/ModuleManagerTest.php
```

## Security

### Security Considerations

- **Input Validation**: Always validate and sanitize input
- **SQL Injection**: Use parameterized queries
- **XSS Prevention**: Escape output appropriately
- **Authentication**: Implement proper authentication checks
- **Authorization**: Verify user permissions

### Reporting Security Issues

**DO NOT** report security vulnerabilities through public issues.

Instead, email security@tai-crm.com with:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if available)

We will respond within 48 hours and work with you to resolve the issue.

## Development Tools

### Useful Commands

```bash
# Code style fixing
composer style:fix

# Static analysis
composer analyse

# Generate module
php artisan module:make TestModule --aggregate=Product

# Run health checks
php artisan module:health --all

# Performance monitoring
php artisan module:metrics --system

# Security scanning
php artisan module:security --scan --all
```

### IDE Configuration

We recommend using PhpStorm or VS Code with the following extensions:

**PhpStorm:**
- Laravel Plugin
- PHP Annotations
- PHP Inspections (EA Extended)

**VS Code:**
- PHP Intelephense
- Laravel Extension Pack
- PHP Debug
- GitLens

## Community Guidelines

### Communication

- **Be respectful** and professional
- **Stay on topic** in discussions
- **Help others** when you can
- **Ask questions** when you need help
- **Share knowledge** and experiences

### Getting Help

- **GitHub Discussions**: For general questions and discussions
- **GitHub Issues**: For bug reports and feature requests
- **Discord**: For real-time community chat
- **Stack Overflow**: Tag questions with `laravel-modular-ddd`

## Recognition

Contributors are recognized in:

- **CHANGELOG.md**: Major contributions
- **README.md**: Core contributors
- **GitHub Contributors**: Automatic recognition
- **Release Notes**: Notable contributions

## Release Process

Releases follow semantic versioning:

- **Major**: Breaking changes
- **Minor**: New features (backward compatible)
- **Patch**: Bug fixes (backward compatible)

## Questions?

If you have questions about contributing:

1. Check the [documentation](docs/)
2. Search [existing issues](https://github.com/tai-crm/laravel-modular-ddd/issues)
3. Ask in [GitHub Discussions](https://github.com/tai-crm/laravel-modular-ddd/discussions)
4. Join our [Discord community](https://discord.gg/laravel-modular-ddd)
5. Email us at support@tai-crm.com

Thank you for contributing to Laravel Modular DDD! 🚀