# Laravel Modular DDD - Full Cycle Test Script

This comprehensive test script validates the complete functionality of the Laravel Modular DDD package.

## 🚀 Quick Start

```bash
# Run all tests
./test-cycle.sh

# Run specific test category
./test-cycle.sh health
```

## 📋 Test Categories

| Command | Description |
|---------|-------------|
| `full` | Run all tests (default) |
| `package` | Test package information and commands |
| `creation` | Test module creation with templates |
| `installation` | Test module installation and enabling |
| `health` | Test health check functionality |
| `listing` | Test module listing commands |
| `metrics` | Test module metrics collection |
| `dependencies` | Test dependency management |
| `security` | Test security scanning |
| `visualization` | Test dependency visualization |
| `performance` | Test performance with multiple modules |
| `cleanup` | Clean up test modules |

## 🧪 What Gets Tested

### 1. Package Information Test
- ✅ Package version verification
- ✅ Available commands listing
- ✅ Basic package functionality

### 2. Module Creation Test
- ✅ Module directory structure generation
- ✅ Template file creation (Domain, Application, Infrastructure, Presentation layers)
- ✅ Manifest.json generation with proper display names
- ✅ Route file template variable replacement
- ✅ Service provider creation

### 3. Module Installation Test
- ✅ Module installation process
- ✅ Module enabling functionality
- ✅ Status verification

### 4. Health Check Test
- ✅ Single module health checking
- ✅ Detailed health diagnostics
- ✅ All modules health overview
- ✅ Color formatting and status icons

### 5. Module Listing Test
- ✅ Module list display
- ✅ Module status overview
- ✅ Enabled/disabled state tracking

### 6. Module Metrics Test
- ✅ Performance metrics collection
- ✅ Single module metrics
- ✅ System-wide metrics overview

### 7. Dependency Management Test
- ✅ Dependent module creation
- ✅ Dependency resolution validation
- ✅ Health checks with dependencies

### 8. Security Scan Test
- ✅ Individual module security scanning
- ✅ System-wide security validation
- ✅ Vulnerability detection

### 9. Module Visualization Test
- ✅ Dependency graph generation
- ✅ SVG output creation
- ✅ Visual module relationships

### 10. Performance Test
- ✅ Multiple module creation performance
- ✅ Health check performance with scale
- ✅ Command execution timing

### 11. Cleanup Test
- ✅ Module disabling
- ✅ Module removal
- ✅ Test artifact cleanup

## 🛠️ Configuration

The script uses these default settings:

```bash
TEST_MODULE="TestCycle"
TEST_AGGREGATE="Product"
CRM_PROJECT_PATH="/Users/macbook/sites/TAI-CRM/CRM"
```

Modify these variables at the top of the script to match your environment.

## 📊 Expected Output

The script provides color-coded output:
- 🔵 **Blue**: Section headers and info
- 🟢 **Green**: Success messages
- 🟡 **Yellow**: Warnings
- 🔴 **Red**: Errors

### Sample Output
```
========================================
 1. Package Information Test
========================================

ℹ️  Checking package version...
versions : * 1.1.4
✅ Package information verified

========================================
 2. Module Creation Test
========================================

ℹ️  Creating test module: TestCycle with aggregate: Product
✅ Module 'TestCycle' created successfully!
✅ Found: modules/TestCycle/manifest.json
✅ Module creation test completed
```

## 🔧 Error Handling

The script includes comprehensive error handling:
- Automatic cleanup on failure
- Exit on first error with line number
- Descriptive error messages
- Rollback of test changes

## ⚡ Performance Monitoring

The script tracks:
- Individual test execution time
- Total test suite duration
- Command performance metrics
- Resource usage patterns

## 🧹 Cleanup

The script automatically cleans up:
- Test modules created during testing
- Generated files (SVG, logs, etc.)
- Temporary dependencies
- Cache files

## 🚨 Prerequisites

- Laravel Modular DDD package installed
- PHP 8.1+ with required extensions
- Composer with package access
- Write permissions in modules directory
- Python 3 (for JSON manipulation)

## 💡 Usage Examples

```bash
# Quick health check test only
./test-cycle.sh health

# Test just module creation and installation
./test-cycle.sh creation && ./test-cycle.sh installation

# Performance testing
./test-cycle.sh performance

# Full test with timing
time ./test-cycle.sh full
```

This test script ensures the Laravel Modular DDD package works correctly across all its features and provides confidence for production deployment.