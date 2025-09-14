# Enhanced Performance Monitoring System

This comprehensive performance monitoring system provides deep insights into your modular Laravel application's performance, helping identify bottlenecks and optimization opportunities.

## Core Components

### 🔍 Query Performance Analyzer
Monitors and analyzes database query performance in real-time.

**Features:**
- Query execution time tracking
- Slow query detection with configurable thresholds
- N+1 query pattern detection
- Query optimization recommendations
- Performance metrics caching

**Usage:**
```php
use TaiCrm\LaravelModularDdd\Monitoring\QueryPerformanceAnalyzer;

$analyzer = app(QueryPerformanceAnalyzer::class);

// Start monitoring
$analyzer->startMonitoring();

// Your application code here
User::with('posts')->get(); // This will be monitored

// Stop and get results
$report = $analyzer->stopMonitoring();

// Check for issues
$slowQueries = $analyzer->getSlowQueries();
$nPlusOneQueries = $analyzer->detectNPlusOneQueries();
```

### 🗄️ Cache Performance Monitor
Tracks cache hit/miss rates and identifies optimization opportunities.

**Features:**
- Hit/miss rate monitoring
- Key pattern analysis
- Cache efficiency tracking
- Module-specific cache usage analysis
- Optimization suggestions

**Usage:**
```php
use TaiCrm\LaravelModularDdd\Monitoring\CachePerformanceMonitor;

$monitor = app(CachePerformanceMonitor::class);

// Start monitoring with patterns
$monitor->startMonitoring(['user:*', 'query:*']);

// Your cached operations
Cache::get('user:123');
Cache::put('user:456', $userData);

// Get analysis results
$report = $monitor->stopMonitoring();
$hitRate = $monitor->getHitRate();
$suggestions = $monitor->optimizeCacheKeys();
```

### 💾 Module Resource Monitor
Analyzes module resource usage and provides optimization recommendations.

**Features:**
- Memory usage tracking
- File count and disk usage analysis
- Class and route counting
- Dependency analysis
- Resource threshold monitoring

**Usage:**
```php
use TaiCrm\LaravelModularDdd\Monitoring\ModuleResourceMonitor;

$monitor = app(ModuleResourceMonitor::class);

// Monitor single module
$monitor->startMonitoring('UserModule');
// Module operations...
$metrics = $monitor->stopMonitoring('UserModule');

// Get comprehensive resource usage
$allUsage = $monitor->getAllModulesResourceUsage();
$report = $monitor->generateResourceReport();
```

### 🚀 Enhanced Performance Middleware
Comprehensive HTTP request performance monitoring middleware.

**Features:**
- Request execution time tracking
- Memory usage monitoring
- Query and cache performance correlation
- Performance headers injection
- Automatic alerting for performance issues

**Usage:**
```php
// In your HTTP kernel or route middleware
use TaiCrm\LaravelModularDdd\Monitoring\EnhancedPerformanceMiddleware;

// Global middleware
protected $middleware = [
    EnhancedPerformanceMiddleware::class,
];

// Route-specific monitoring
Route::get('/api/users', [UserController::class, 'index'])
    ->middleware('enhanced.performance');
```

## Performance Analysis Command

### Command Usage

```bash
# Analyze all performance aspects
php artisan module:performance:analyze

# Analyze specific module
php artisan module:performance:analyze --module=UserModule

# Analyze specific performance type
php artisan module:performance:analyze --type=queries
php artisan module:performance:analyze --type=cache
php artisan module:performance:analyze --type=resources

# Continuous monitoring mode
php artisan module:performance:analyze --watch --duration=120

# Export results
php artisan module:performance:analyze --export=performance-report.json
```

### Sample Output

```
🔍 Module Performance Analysis

📊 Query Performance Metrics
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
┌─────────────────────┬────────────┐
│ Metric              │ Value      │
├─────────────────────┼────────────┤
│ Total Queries       │ 45         │
│ Average Time        │ 23.5ms     │
│ Total Time          │ 1057.5ms   │
│ Slow Queries        │ 3          │
│ Slow Query Threshold│ 1000ms     │
└─────────────────────┴────────────┘

⚠️  Slow Queries Detected:
  • 1250ms: SELECT * FROM users WHERE status = ? ORDER BY created_at DESC...
  • 1100ms: SELECT * FROM posts WHERE user_id IN (?, ?, ?, ?) AND published...

🗄️  Cache Performance Metrics
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
┌─────────────────┬────────────┐
│ Metric          │ Value      │
├─────────────────┼────────────┤
│ Cache Hits      │ 125        │
│ Cache Misses    │ 35         │
│ Hit Rate        │ 78.12%     │
│ Miss Rate       │ 21.88%     │
│ Total Operations│ 160        │
└─────────────────┴────────────┘

💡 Performance Recommendations
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🟠 [high] Found 3 slow queries. Consider adding indexes or optimizing query structure.
   Action: Review slow queries and add appropriate database indexes.

🟡 [medium] Cache hit rate is 78.12%. Consider reviewing cache strategy.
   Action: Increase TTL for frequently accessed data or implement cache warming.
```

## Performance Monitoring Dashboard Integration

### Real-time Metrics Collection

```php
// In your application
use TaiCrm\LaravelModularDdd\Monitoring\QueryPerformanceAnalyzer;
use TaiCrm\LaravelModularDdd\Monitoring\CachePerformanceMonitor;

class PerformanceDashboardController
{
    public function getMetrics(Request $request)
    {
        $queryAnalyzer = app(QueryPerformanceAnalyzer::class);
        $cacheMonitor = app(CachePerformanceMonitor::class);

        return response()->json([
            'queries' => $queryAnalyzer->getQueryStats(),
            'cache' => [
                'hit_rate' => $cacheMonitor->getHitRate(),
                'total_operations' => $cacheMonitor->getMetrics()['hits'] + $cacheMonitor->getMetrics()['misses'],
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }
}
```

## Advanced Configuration

### Custom Thresholds

```php
// Configure performance thresholds
$queryAnalyzer = app(QueryPerformanceAnalyzer::class);
$queryAnalyzer->setSlowQueryThreshold(500); // 500ms

$resourceMonitor = app(ModuleResourceMonitor::class);
$resourceMonitor->setThreshold('memory_usage', 256 * 1024 * 1024); // 256MB
$resourceMonitor->setThreshold('execution_time', 3000); // 3 seconds
```

### Performance Alerting

```php
// Custom performance alert handler
class PerformanceAlertHandler
{
    public function handleAlert(array $alertData): void
    {
        if ($alertData['severity'] === 'critical') {
            // Send to monitoring service
            $this->sendToMonitoringService($alertData);

            // Notify team
            $this->notifyTeam($alertData);
        }

        // Log for historical analysis
        Log::channel('performance')->alert('Performance alert', $alertData);
    }
}
```

## Performance Optimization Patterns

### 1. Query Optimization

```php
// Before: N+1 Query Problem
$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count(); // N+1 queries
}

// After: Eager Loading
$users = User::withCount('posts')->get();
foreach ($users as $user) {
    echo $user->posts_count; // Single query
}
```

### 2. Cache Optimization

```php
// Smart cache warming
class CacheWarmupService
{
    public function warmupFrequentlyMissedKeys(): void
    {
        $monitor = app(CachePerformanceMonitor::class);
        $missedKeys = $monitor->getTopMissedKeys(10);

        foreach ($missedKeys as $key => $count) {
            if ($count > 10) {
                $this->warmupKey($key);
            }
        }
    }
}
```

### 3. Resource Optimization

```php
// Module lazy loading
class ModuleLoader
{
    public function loadModulesOnDemand(): void
    {
        $resourceMonitor = app(ModuleResourceMonitor::class);
        $usage = $resourceMonitor->getAllModulesResourceUsage();

        // Only load essential modules initially
        foreach ($usage as $moduleId => $stats) {
            if ($stats['priority'] === 'high') {
                $this->moduleManager->enable($moduleId);
            }
        }
    }
}
```

## Monitoring Best Practices

### 1. **Continuous Monitoring**
- Enable monitoring in production with appropriate sampling
- Set up automated alerts for critical performance degradation
- Regular performance audits using the analysis command

### 2. **Threshold Management**
- Define realistic performance thresholds based on your application requirements
- Adjust thresholds based on historical performance data
- Different thresholds for different environments (dev/staging/production)

### 3. **Performance Budgets**
- Set performance budgets for each module
- Monitor resource usage trends over time
- Implement performance regression testing

### 4. **Optimization Workflow**
```bash
# 1. Identify performance issues
php artisan module:performance:analyze --watch --duration=300

# 2. Export detailed report
php artisan module:performance:analyze --export=before-optimization.json

# 3. Apply optimizations

# 4. Compare results
php artisan module:performance:analyze --export=after-optimization.json
```

This enhanced performance monitoring system provides comprehensive insights into your application's performance, enabling proactive optimization and ensuring optimal user experience across all modules.