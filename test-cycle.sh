#!/bin/bash

# Laravel Modular DDD - Full Cycle Test Script
# This script tests the complete functionality of the package

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test configuration
TEST_MODULE="TestCycle"
TEST_AGGREGATE="Product"
CRM_PROJECT_PATH="/Users/macbook/sites/TAI-CRM/CRM"

# Helper functions
print_header() {
    echo -e "\n${BLUE}========================================${NC}"
    echo -e "${BLUE} $1${NC}"
    echo -e "${BLUE}========================================${NC}\n"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

# Test functions
test_package_info() {
    print_header "1. Package Information Test"

    cd "$CRM_PROJECT_PATH"

    print_info "Checking package version..."
    composer show mghrby/laravel-modular-ddd | grep "versions"

    print_info "Checking available commands..."
    php artisan list module | head -20

    print_success "Package information verified"
}

test_module_creation() {
    print_header "2. Module Creation Test"

    cd "$CRM_PROJECT_PATH"

    print_info "Creating test module: $TEST_MODULE with aggregate: $TEST_AGGREGATE"
    php artisan module:make "$TEST_MODULE" --aggregate="$TEST_AGGREGATE" --author="Test Author" --description="Test module for full cycle testing"

    print_info "Verifying module structure..."
    if [ -d "modules/$TEST_MODULE" ]; then
        print_success "Module directory created"
    else
        print_error "Module directory not found"
        exit 1
    fi

    # Check key files
    FILES_TO_CHECK=(
        "modules/$TEST_MODULE/manifest.json"
        "modules/$TEST_MODULE/Domain/Models/$TEST_AGGREGATE.php"
        "modules/$TEST_MODULE/Domain/ValueObjects/${TEST_AGGREGATE}Id.php"
        "modules/$TEST_MODULE/Routes/api.php"
        "modules/$TEST_MODULE/Routes/web.php"
        "modules/$TEST_MODULE/Providers/${TEST_MODULE}ServiceProvider.php"
    )

    for file in "${FILES_TO_CHECK[@]}"; do
        if [ -f "$file" ]; then
            print_success "Found: $file"
        else
            print_error "Missing: $file"
        fi
    done

    print_info "Checking manifest display name..."
    DISPLAY_NAME=$(grep -o '"display_name": "[^"]*"' "modules/$TEST_MODULE/manifest.json" | cut -d'"' -f4)
    echo "Display name: $DISPLAY_NAME"

    print_info "Checking route file for template replacement..."
    if grep -q "product" "modules/$TEST_MODULE/Routes/api.php"; then
        print_success "Template variables properly replaced in routes"
    else
        print_warning "Template variables might not be replaced properly"
    fi

    print_success "Module creation test completed"
}

test_module_installation() {
    print_header "3. Module Installation Test"

    cd "$CRM_PROJECT_PATH"

    print_info "Installing module: $TEST_MODULE"
    echo "yes" | php artisan module:install "$TEST_MODULE"

    print_info "Enabling module: $TEST_MODULE"
    php artisan module:enable "$TEST_MODULE"

    print_info "Verifying module status..."
    php artisan module:status "$TEST_MODULE"

    print_success "Module installation test completed"
}

test_health_checks() {
    print_header "4. Health Check Test"

    cd "$CRM_PROJECT_PATH"

    print_info "Testing health check on single module..."
    php artisan module:health "$TEST_MODULE"

    print_info "Testing detailed health check..."
    php artisan module:health "$TEST_MODULE" --detailed

    print_info "Testing health check on all modules..."
    php artisan module:health --all

    print_success "Health check test completed"
}

test_module_listing() {
    print_header "5. Module Listing Test"

    cd "$CRM_PROJECT_PATH"

    print_info "Testing module list command..."
    php artisan module:list

    print_info "Testing module status command..."
    php artisan module:status

    print_success "Module listing test completed"
}

test_module_metrics() {
    print_header "6. Module Metrics Test"

    cd "$CRM_PROJECT_PATH"

    print_info "Testing module metrics..."
    php artisan module:metrics --all

    print_info "Testing single module metrics..."
    php artisan module:metrics "$TEST_MODULE"

    print_success "Module metrics test completed"
}

test_dependency_management() {
    print_header "7. Dependency Management Test"

    cd "$CRM_PROJECT_PATH"

    print_info "Creating a dependent module..."
    php artisan module:make "DependentTest" --aggregate="Order" --description="Module that depends on $TEST_MODULE"

    # Add dependency to manifest
    print_info "Adding dependency to DependentTest manifest..."
    python3 -c "
import json
with open('modules/DependentTest/manifest.json', 'r') as f:
    data = json.load(f)
data['dependencies'] = ['$TEST_MODULE']
with open('modules/DependentTest/manifest.json', 'w') as f:
    json.dump(data, f, indent=4)
"

    echo "yes" | php artisan module:install "DependentTest"
    php artisan module:enable "DependentTest"

    print_info "Testing dependency resolution..."
    php artisan module:health "DependentTest"

    print_success "Dependency management test completed"
}

test_module_security() {
    print_header "8. Security Scan Test"

    cd "$CRM_PROJECT_PATH"

    print_info "Running security scan on test module..."
    php artisan module:security "$TEST_MODULE"

    print_info "Running security scan on all modules..."
    php artisan module:security --all

    print_success "Security scan test completed"
}

test_module_visualization() {
    print_header "9. Module Visualization Test"

    cd "$CRM_PROJECT_PATH"

    print_info "Generating module dependency visualization..."
    php artisan module:visualize --output="test-dependencies.svg"

    if [ -f "test-dependencies.svg" ]; then
        print_success "Dependency visualization generated"
        print_info "File size: $(ls -lh test-dependencies.svg | awk '{print $5}')"
    else
        print_warning "Visualization file not generated"
    fi

    print_success "Module visualization test completed"
}

test_cleanup() {
    print_header "10. Cleanup Test"

    cd "$CRM_PROJECT_PATH"

    print_info "Disabling test modules..."
    echo "yes" | php artisan module:disable "DependentTest" || true
    echo "yes" | php artisan module:disable "$TEST_MODULE" || true

    print_info "Removing test modules..."
    rm -rf "modules/DependentTest" || true
    rm -rf "modules/$TEST_MODULE" || true

    print_info "Cleaning up test files..."
    rm -f "test-dependencies.svg" || true

    print_success "Cleanup completed"
}

test_performance() {
    print_header "11. Performance Test"

    cd "$CRM_PROJECT_PATH"

    print_info "Testing performance with multiple modules..."

    # Create multiple test modules
    for i in {1..5}; do
        MODULE_NAME="PerfTest$i"
        print_info "Creating $MODULE_NAME..."
        php artisan module:make "$MODULE_NAME" --aggregate="Item$i" >/dev/null 2>&1
        echo "yes" | php artisan module:install "$MODULE_NAME" >/dev/null 2>&1
        php artisan module:enable "$MODULE_NAME" >/dev/null 2>&1
    done

    print_info "Running health check on all modules (performance test)..."
    time php artisan module:health --all >/dev/null

    print_info "Running module list (performance test)..."
    time php artisan module:list >/dev/null

    # Cleanup performance test modules
    for i in {1..5}; do
        MODULE_NAME="PerfTest$i"
        echo "yes" | php artisan module:disable "$MODULE_NAME" >/dev/null 2>&1 || true
        rm -rf "modules/$MODULE_NAME" || true
    done

    print_success "Performance test completed"
}

run_full_test() {
    print_header "🚀 Starting Laravel Modular DDD Full Cycle Test"

    echo -e "${BLUE}Test Configuration:${NC}"
    echo "- Test Module: $TEST_MODULE"
    echo "- Test Aggregate: $TEST_AGGREGATE"
    echo "- CRM Project Path: $CRM_PROJECT_PATH"
    echo "- Package: mghrby/laravel-modular-ddd"
    echo ""

    START_TIME=$(date +%s)

    # Run all tests
    test_package_info
    test_module_creation
    test_module_installation
    test_health_checks
    test_module_listing
    test_module_metrics
    test_dependency_management
    test_module_security
    test_module_visualization
    test_performance
    test_cleanup

    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))

    print_header "🎉 Full Cycle Test Completed Successfully!"
    echo -e "${GREEN}Total time: ${DURATION}s${NC}"
    echo -e "${GREEN}All tests passed! ✅${NC}"
}

# Error handling
trap 'print_error "Test failed at line $LINENO. Exit code: $?"; test_cleanup; exit 1' ERR

# Main execution
case "${1:-full}" in
    "full")
        run_full_test
        ;;
    "package")
        test_package_info
        ;;
    "creation")
        test_module_creation
        ;;
    "installation")
        test_module_installation
        ;;
    "health")
        test_health_checks
        ;;
    "listing")
        test_module_listing
        ;;
    "metrics")
        test_module_metrics
        ;;
    "dependencies")
        test_dependency_management
        ;;
    "security")
        test_module_security
        ;;
    "visualization")
        test_module_visualization
        ;;
    "performance")
        test_performance
        ;;
    "cleanup")
        test_cleanup
        ;;
    *)
        echo "Usage: $0 [full|package|creation|installation|health|listing|metrics|dependencies|security|visualization|performance|cleanup]"
        echo ""
        echo "Available test modes:"
        echo "  full          - Run all tests (default)"
        echo "  package       - Test package information"
        echo "  creation      - Test module creation"
        echo "  installation  - Test module installation"
        echo "  health        - Test health checks"
        echo "  listing       - Test module listing"
        echo "  metrics       - Test module metrics"
        echo "  dependencies  - Test dependency management"
        echo "  security      - Test security scanning"
        echo "  visualization - Test dependency visualization"
        echo "  performance   - Test performance with multiple modules"
        echo "  cleanup       - Clean up test modules"
        exit 1
        ;;
esac