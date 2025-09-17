<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ModuleMakeMiddlewareCommand extends Command
{
    protected $signature = 'module:make-middleware {module} {name} {--auth} {--rate-limit} {--cors}';
    protected $description = 'Create a new middleware for a module';

    public function handle(): int
    {
        $moduleName = $this->argument('module');
        $middlewareName = $this->argument('name');
        $withAuth = $this->option('auth');
        $withRateLimit = $this->option('rate-limit');
        $withCors = $this->option('cors');

        if (!$this->moduleExists($moduleName)) {
            $this->error("Module '{$moduleName}' does not exist.");

            return 1;
        }

        $this->createMiddleware($moduleName, $middlewareName, $withAuth, $withRateLimit, $withCors);

        $this->info("Middleware '{$middlewareName}' created successfully for module '{$moduleName}'.");
        $this->line("📝 Don't forget to register it in your module's service provider or HTTP kernel.");

        return 0;
    }

    private function moduleExists(string $moduleName): bool
    {
        return is_dir(base_path("modules/{$moduleName}"));
    }

    private function createMiddleware(string $moduleName, string $middlewareName, bool $withAuth, bool $withRateLimit, bool $withCors): void
    {
        $middlewareDir = base_path("modules/{$moduleName}/Http/Middleware");
        $this->ensureDirectoryExists($middlewareDir);

        $middlewareFile = "{$middlewareDir}/{$middlewareName}.php";

        if ($withAuth) {
            $template = $this->getAuthMiddlewareTemplate();
        } elseif ($withRateLimit) {
            $template = $this->getRateLimitMiddlewareTemplate();
        } elseif ($withCors) {
            $template = $this->getCorsMiddlewareTemplate();
        } else {
            $template = $this->getBasicMiddlewareTemplate();
        }

        $replacements = [
            '{{MODULE_NAMESPACE}}' => "Modules\\{$moduleName}",
            '{{MIDDLEWARE_NAME}}' => $middlewareName,
            '{{MIDDLEWARE_VARIABLE}}' => Str::camel($middlewareName),
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);
        file_put_contents($middlewareFile, $content);
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }
    }

    private function getBasicMiddlewareTemplate(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace {{MODULE_NAMESPACE}}\Http\Middleware;

            use Closure;
            use Illuminate\Http\Request;
            use Symfony\Component\HttpFoundation\Response;

            class {{MIDDLEWARE_NAME}}
            {
                /**
                 * Handle an incoming request.
                 */
                public function handle(Request $request, Closure $next): Response
                {
                    // TODO: Implement middleware logic before the request

                    $response = $next($request);

                    // TODO: Implement middleware logic after the request

                    return $response;
                }
            }
            PHP;
    }

    private function getAuthMiddlewareTemplate(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace {{MODULE_NAMESPACE}}\Http\Middleware;

            use Closure;
            use Illuminate\Http\Request;
            use Illuminate\Support\Facades\Auth;
            use Symfony\Component\HttpFoundation\Response;

            class {{MIDDLEWARE_NAME}}
            {
                /**
                 * Handle an incoming request.
                 */
                public function handle(Request $request, Closure $next, string $guard = null): Response
                {
                    if (!Auth::guard($guard)->check()) {
                        return response()->json([
                            'message' => 'Unauthenticated'
                        ], 401);
                    }

                    $user = Auth::guard($guard)->user();

                    // TODO: Add additional authorization logic here
                    // Example: Check user permissions, roles, etc.

                    return $next($request);
                }
            }
            PHP;
    }

    private function getRateLimitMiddlewareTemplate(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace {{MODULE_NAMESPACE}}\Http\Middleware;

            use Closure;
            use Illuminate\Http\Request;
            use Illuminate\Support\Facades\Cache;
            use Illuminate\Support\Facades\RateLimiter;
            use Symfony\Component\HttpFoundation\Response;

            class {{MIDDLEWARE_NAME}}
            {
                /**
                 * Handle an incoming request.
                 */
                public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1): Response
                {
                    $key = $this->resolveRequestSignature($request);

                    if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                        $seconds = RateLimiter::availableIn($key);

                        return response()->json([
                            'message' => 'Too many requests. Please try again in ' . $seconds . ' seconds.',
                            'retry_after' => $seconds
                        ], 429);
                    }

                    RateLimiter::hit($key, $decayMinutes * 60);

                    $response = $next($request);

                    // Add rate limit headers
                    $response->headers->set('X-RateLimit-Limit', $maxAttempts);
                    $response->headers->set('X-RateLimit-Remaining', RateLimiter::remaining($key, $maxAttempts));

                    return $response;
                }

                /**
                 * Resolve request signature for rate limiting.
                 */
                protected function resolveRequestSignature(Request $request): string
                {
                    if ($user = $request->user()) {
                        return sha1($user->getAuthIdentifier());
                    }

                    return sha1($request->ip());
                }
            }
            PHP;
    }

    private function getCorsMiddlewareTemplate(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace {{MODULE_NAMESPACE}}\Http\Middleware;

            use Closure;
            use Illuminate\Http\Request;
            use Symfony\Component\HttpFoundation\Response;

            class {{MIDDLEWARE_NAME}}
            {
                /**
                 * Handle an incoming request.
                 */
                public function handle(Request $request, Closure $next): Response
                {
                    $response = $next($request);

                    // Add CORS headers
                    $response->headers->set('Access-Control-Allow-Origin', '*');
                    $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
                    $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
                    $response->headers->set('Access-Control-Allow-Credentials', 'true');
                    $response->headers->set('Access-Control-Max-Age', '86400');

                    // Handle preflight requests
                    if ($request->isMethod('OPTIONS')) {
                        return response('', 200)
                            ->header('Access-Control-Allow-Origin', '*')
                            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                            ->header('Access-Control-Allow-Credentials', 'true')
                            ->header('Access-Control-Max-Age', '86400');
                    }

                    return $response;
                }
            }
            PHP;
    }
}
