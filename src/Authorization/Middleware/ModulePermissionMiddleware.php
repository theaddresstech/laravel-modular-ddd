<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Authorization\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use TaiCrm\LaravelModularDdd\Authorization\ModulePermissionManager;

class ModulePermissionMiddleware
{
    public function __construct(
        private ModulePermissionManager $permissionManager,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param string $permission Format: "module.permission" or "module.*"
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = Auth::user();

        if (!$user) {
            return $this->unauthorized('Authentication required');
        }

        // Parse permission format
        [$moduleId, $permissionName] = $this->parsePermission($permission);

        // Check module access if wildcard permission
        if ($permissionName === '*') {
            if (!$user->hasModuleAccess($moduleId)) {
                return $this->forbidden("Access denied to module: {$moduleId}");
            }
        } else {
            // Check specific permission
            if (!$this->permissionManager->userHasModulePermission($user, $moduleId, $permissionName)) {
                return $this->forbidden("Permission denied: {$permission}");
            }
        }

        // Add permission context to request
        $request->attributes->set('module_id', $moduleId);
        $request->attributes->set('permission', $permissionName);
        $request->attributes->set('user_permissions', $this->authManager->getUserModulePermissions($user, $moduleId));

        return $next($request);
    }

    private function parsePermission(string $permission): array
    {
        $parts = explode('.', $permission, 2);

        if (count($parts) !== 2) {
            throw new InvalidArgumentException("Invalid permission format: {$permission}. Expected format: 'module.permission'");
        }

        return $parts;
    }

    private function unauthorized(string $message): Response
    {
        if (request()->expectsJson()) {
            return response()->json([
                'message' => $message,
                'error' => 'unauthorized',
            ], 401);
        }

        return redirect()->guest(route('login'))->withErrors(['message' => $message]);
    }

    private function forbidden(string $message): Response
    {
        if (request()->expectsJson()) {
            return response()->json([
                'message' => $message,
                'error' => 'forbidden',
            ], 403);
        }

        abort(403, $message);
    }
}
