# Laravel Modular DDD Package Makefile
# Provides convenient shortcuts for development and release tasks

.PHONY: help install test lint fix analyze coverage docs clean release validate

# Default target
help: ## Show this help message
	@echo "Laravel Modular DDD Package - Available Commands:"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'
	@echo ""
	@echo "Examples:"
	@echo "  make install    # Install dependencies"
	@echo "  make test       # Run test suite"
	@echo "  make release    # Create a new release"

# Development Setup
install: ## Install all dependencies
	composer install
	@echo "Dependencies installed successfully"

install-dev: ## Install development dependencies
	composer install --dev
	@echo "Development dependencies installed"

update: ## Update dependencies
	composer update
	@echo "Dependencies updated"

# Testing
test: ## Run the test suite
	vendor/bin/phpunit

test-unit: ## Run unit tests only
	vendor/bin/phpunit --testsuite=Unit

test-integration: ## Run integration tests only
	vendor/bin/phpunit --testsuite=Integration

test-feature: ## Run feature tests only
	vendor/bin/phpunit --testsuite=Feature

coverage: ## Generate test coverage report
	vendor/bin/phpunit --coverage-html coverage-report
	@echo "Coverage report generated in coverage-report/"

# Code Quality
lint: ## Run code style checks
	vendor/bin/php-cs-fixer fix --dry-run --diff

fix: ## Fix code style issues
	vendor/bin/php-cs-fixer fix
	@echo "Code style fixed"

analyze: ## Run static analysis
	vendor/bin/psalm --no-progress

analyze-taint: ## Run taint analysis for security
	vendor/bin/psalm --taint-analysis --no-progress

security: ## Run security audit
	composer audit

# Package Validation
validate: ## Validate package structure and integrity
	composer validate-package

validate-composer: ## Validate composer.json
	composer validate --strict

# Documentation
docs-serve: ## Serve documentation locally
	@if [ -d "docs" ]; then \
		cd docs && python3 -m http.server 8080; \
	else \
		echo "Documentation directory not found"; \
	fi

# Release Management
pre-release: validate test analyze ## Run pre-release checks
	@echo "Pre-release checks completed successfully"

release: ## Create a new release (specify VERSION=x.x.x)
	@if [ -z "$(VERSION)" ]; then \
		echo "Please specify a version: make release VERSION=1.0.0"; \
		exit 1; \
	fi
	./scripts/release.sh $(VERSION)

tag: ## Create and push a git tag (specify VERSION=x.x.x)
	@if [ -z "$(VERSION)" ]; then \
		echo "Please specify a version: make tag VERSION=1.0.0"; \
		exit 1; \
	fi
	git tag -a "v$(VERSION)" -m "Release version $(VERSION)"
	git push origin "v$(VERSION)"
	@echo "Tag v$(VERSION) created and pushed"

# Cleanup
clean: ## Clean temporary files and caches
	rm -rf vendor/
	rm -rf coverage-report/
	rm -rf .phpunit.result.cache
	rm -f composer.lock
	@echo "Cleaned temporary files"

clean-cache: ## Clear Laravel caches
	php artisan cache:clear || true
	php artisan config:clear || true
	php artisan route:clear || true
	php artisan view:clear || true

# Docker Operations
docker-build: ## Build Docker image
	docker build -f docker/Dockerfile -t taicrm/laravel-modular-ddd:dev .

docker-test: ## Run tests in Docker container
	docker run --rm -v $(PWD):/app taicrm/laravel-modular-ddd:dev composer test

docker-validate: ## Validate package in Docker container
	docker run --rm -v $(PWD):/app taicrm/laravel-modular-ddd:dev php scripts/validate-package.php

# Development Helpers
watch: ## Watch for file changes and run tests
	@if command -v fswatch >/dev/null 2>&1; then \
		fswatch -o src/ tests/ | xargs -n1 -I{} make test; \
	elif command -v inotifywait >/dev/null 2>&1; then \
		while inotifywait -r -e modify src/ tests/; do make test; done; \
	else \
		echo "Please install fswatch (macOS) or inotify-tools (Linux) to use watch mode"; \
	fi

dev-setup: install-dev ## Complete development environment setup
	@echo "Setting up development environment..."
	mkdir -p coverage-report
	@if [ ! -f .env ]; then cp .env.example .env; fi
	@echo "Development environment ready!"

# CI/CD Helpers
ci-install: ## Install dependencies for CI
	composer install --prefer-dist --no-progress --no-suggest --optimize-autoloader

ci-test: ## Run tests suitable for CI
	vendor/bin/phpunit --coverage-clover=coverage.xml --log-junit=test-results.xml

ci-validate: ## Run all validation checks for CI
	make validate
	make lint
	make analyze
	make security

# Benchmarking
benchmark: ## Run performance benchmarks
	@if [ -f "benchmarks/run.php" ]; then \
		php benchmarks/run.php; \
	else \
		echo "No benchmarks found"; \
	fi

# Package Information
info: ## Show package information
	@echo "Package: tai-crm/laravel-modular-ddd"
	@echo "Version: $$(cat composer.json | grep '"version"' | cut -d'"' -f4)"
	@echo "PHP Version: $$(php -v | head -n1)"
	@echo "Composer Version: $$(composer --version)"
	@echo ""
	@echo "Dependencies:"
	@composer show --tree --no-dev | head -20

check-updates: ## Check for dependency updates
	composer outdated --direct

# Example and Demo
demo-install: ## Install demo application
	@echo "Installing demo application..."
	@if [ -d "examples/demo-app" ]; then \
		cd examples/demo-app && composer install; \
		echo "Demo application installed. See examples/demo-app/README.md"; \
	else \
		echo "Demo application not found in examples/demo-app"; \
	fi

# Quick shortcuts
t: test ## Shortcut for test
l: lint ## Shortcut for lint
a: analyze ## Shortcut for analyze
f: fix ## Shortcut for fix
v: validate ## Shortcut for validate