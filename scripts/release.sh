#!/bin/bash

set -e

# Laravel Modular DDD Package Release Script
# This script automates the release process for the package

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PACKAGE_NAME="tai-crm/laravel-modular-ddd"
CURRENT_DIR=$(pwd)
TEMP_DIR="/tmp/laravel-modular-ddd-release"

# Functions
print_header() {
    echo -e "${BLUE}================================================${NC}"
    echo -e "${BLUE} Laravel Modular DDD Release Script${NC}"
    echo -e "${BLUE}================================================${NC}"
    echo ""
}

print_step() {
    echo -e "${YELLOW}▶ $1${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

check_prerequisites() {
    print_step "Checking prerequisites..."

    # Check if we're in a git repository
    if [ ! -d ".git" ]; then
        print_error "Not in a git repository"
        exit 1
    fi

    # Check for required commands
    commands=("git" "composer" "php" "jq")
    for cmd in "${commands[@]}"; do
        if ! command -v $cmd &> /dev/null; then
            print_error "$cmd is required but not installed"
            exit 1
        fi
    done

    # Check PHP version
    php_version=$(php -r "echo PHP_VERSION;" | cut -d. -f1,2)
    if [ "$(echo "$php_version >= 8.2" | bc -l)" -ne 1 ]; then
        print_error "PHP 8.2 or higher is required (current: $php_version)"
        exit 1
    fi

    print_success "All prerequisites met"
}

check_working_directory() {
    print_step "Checking working directory..."

    if [ -n "$(git status --porcelain)" ]; then
        print_error "Working directory is not clean. Please commit or stash changes."
        git status --short
        exit 1
    fi

    print_success "Working directory is clean"
}

validate_version() {
    local version=$1

    if [[ ! $version =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9-]+)?$ ]]; then
        print_error "Invalid version format. Use semantic versioning (e.g., 1.0.0, 1.0.0-alpha)"
        exit 1
    fi
}

update_version_files() {
    local version=$1
    print_step "Updating version files..."

    # Update composer.json
    if [ -f "composer.json" ]; then
        jq --arg version "$version" '.version = $version' composer.json > composer.json.tmp
        mv composer.json.tmp composer.json
        print_success "Updated composer.json"
    fi

    # Update package version constant if it exists
    if [ -f "src/ModularDddServiceProvider.php" ]; then
        sed -i.bak "s/const VERSION = '[^']*'/const VERSION = '$version'/" src/ModularDddServiceProvider.php
        rm -f src/ModularDddServiceProvider.php.bak
        print_success "Updated ModularDddServiceProvider.php"
    fi
}

run_tests() {
    print_step "Running test suite..."

    # Install dependencies
    composer install --no-dev --optimize-autoloader

    # Run PHPUnit tests
    if [ -f "vendor/bin/phpunit" ]; then
        vendor/bin/phpunit
        print_success "All tests passed"
    else
        print_error "PHPUnit not found. Please install development dependencies."
        exit 1
    fi
}

run_static_analysis() {
    print_step "Running static analysis..."

    # Run Psalm if available
    if [ -f "vendor/bin/psalm" ]; then
        vendor/bin/psalm --no-progress
        print_success "Static analysis passed"
    fi

    # Run PHP CS Fixer if available
    if [ -f "vendor/bin/php-cs-fixer" ]; then
        vendor/bin/php-cs-fixer fix --dry-run --diff
        print_success "Code style check passed"
    fi
}

validate_package_structure() {
    print_step "Validating package structure..."

    required_files=(
        "composer.json"
        "README.md"
        "CHANGELOG.md"
        "CONTRIBUTING.md"
        "LICENSE.md"
        "src/ModularDddServiceProvider.php"
    )

    for file in "${required_files[@]}"; do
        if [ ! -f "$file" ]; then
            print_error "Required file missing: $file"
            exit 1
        fi
    done

    print_success "Package structure is valid"
}

generate_release_notes() {
    local version=$1
    print_step "Generating release notes..."

    # Extract changelog for this version
    if [ -f "CHANGELOG.md" ]; then
        # Create release notes from changelog
        awk -v version="$version" '
            /^## \[/ {
                if ($0 ~ "\\[" version "\\]") {
                    found = 1
                    next
                } else if (found) {
                    exit
                }
            }
            found && /^## \[/ { exit }
            found { print }
        ' CHANGELOG.md > RELEASE_NOTES.md

        print_success "Release notes generated"
    fi
}

create_git_tag() {
    local version=$1
    print_step "Creating git tag..."

    # Commit version changes
    git add -A
    git commit -m "chore: bump version to $version"

    # Create annotated tag
    git tag -a "v$version" -m "Release version $version"

    print_success "Git tag created: v$version"
}

validate_composer_package() {
    print_step "Validating composer package..."

    # Validate composer.json
    composer validate --strict

    # Create a temporary installation to test the package
    rm -rf "$TEMP_DIR"
    mkdir -p "$TEMP_DIR"

    cd "$TEMP_DIR"

    # Create a minimal Laravel project for testing
    composer create-project --prefer-dist laravel/laravel test-app --no-interaction
    cd test-app

    # Add our package as a local repository
    composer config repositories.local path "$CURRENT_DIR"
    composer require "$PACKAGE_NAME:@dev" --no-interaction

    # Test basic functionality
    php artisan vendor:publish --provider="TaiCrm\\LaravelModularDdd\\ModularDddServiceProvider" --force

    cd "$CURRENT_DIR"
    rm -rf "$TEMP_DIR"

    print_success "Package validation completed"
}

show_release_summary() {
    local version=$1

    echo ""
    echo -e "${GREEN}================================================${NC}"
    echo -e "${GREEN} Release Summary${NC}"
    echo -e "${GREEN}================================================${NC}"
    echo -e "${GREEN}Package:${NC} $PACKAGE_NAME"
    echo -e "${GREEN}Version:${NC} $version"
    echo -e "${GREEN}Git Tag:${NC} v$version"
    echo ""
    echo -e "${YELLOW}Next Steps:${NC}"
    echo "1. Push changes: git push origin main"
    echo "2. Push tags: git push origin v$version"
    echo "3. Create GitHub release with generated notes"
    echo "4. Submit to Packagist (if first release)"
    echo ""
}

# Main execution
main() {
    print_header

    # Get version from argument or prompt
    if [ -z "$1" ]; then
        echo -n "Enter version (e.g., 1.0.0): "
        read -r VERSION
    else
        VERSION=$1
    fi

    validate_version "$VERSION"

    echo -e "${BLUE}Releasing version: $VERSION${NC}"
    echo ""

    # Run all checks and preparations
    check_prerequisites
    check_working_directory
    validate_package_structure
    update_version_files "$VERSION"
    run_tests
    run_static_analysis
    validate_composer_package
    generate_release_notes "$VERSION"
    create_git_tag "$VERSION"

    show_release_summary "$VERSION"

    print_success "Release preparation completed successfully!"
}

# Run main function with all arguments
main "$@"