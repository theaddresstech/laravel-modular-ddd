<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Authorization\Middleware;

use TaiCrm\LaravelModularDdd\Authorization\ModuleAuthorizationManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ModuleRoleMiddleware
{
    public function __construct(
        private ModuleAuthorizationManager $authManager
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param string $role Format: "module.role" or multiple roles separated by "|"
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = Auth::user();

        if (!$user) {
            return $this->unauthorized('Authentication required');
        }

        $roles = explode('|', $role);
        $hasRequiredRole = false;

        foreach ($roles as $singleRole) {
            [$moduleId, $roleName] = $this->parseRole(trim($singleRole));

            if ($this->authManager->hasRole($user, $moduleId, $roleName)) {
                $hasRequiredRole = true;
                break;
            }
        }

        if (!$hasRequiredRole) {
            return $this->forbidden("Role required: {$role}");
        }

        // Add role context to request
        $request->attributes->set('user_roles', $this->getUserRoles($user));

        return $next($request);
    }

    private function parseRole(string $role): array
    {
        $parts = explode('.', $role, 2);

        if (count($parts) !== 2) {
            throw new \InvalidArgumentException("Invalid role format: {$role}. Expected format: 'module.role'");
        }

        return $parts;
    }

    private function getUserRoles(mixed $user): array
    {
        $roles = [];

        if (method_exists($user, 'getModuleRoles')) {
            $roles = $user->getModuleRoles();
        }

        return $roles;
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