<?php
// src/Core/Middleware/RoleMiddleware.php
declare(strict_types=1);
namespace Smallwork\Core\Middleware;

use Smallwork\Auth\RoleManager;
use Smallwork\Core\Request;
use Smallwork\Core\Response;

class RoleMiddleware
{
    private string $mode; // 'role' or 'permission'
    private string $required;

    public function __construct(
        private RoleManager $roles,
        string $requiredRole,
    ) {
        $this->mode = 'role';
        $this->required = $requiredRole;
    }

    public static function requirePermission(RoleManager $roles, string $permission): self
    {
        $instance = new self($roles, '');
        $instance->mode = 'permission';
        $instance->required = $permission;
        return $instance;
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $request->getAttribute('user');
        if ($user === null) {
            return Response::json(['error' => 'Forbidden'], 403);
        }

        $userRole = $user['role'] ?? '';

        if ($this->mode === 'role') {
            if ($userRole !== $this->required) {
                return Response::json(['error' => 'Forbidden: insufficient role'], 403);
            }
        } else {
            if (!$this->roles->hasPermission($userRole, $this->required)) {
                return Response::json(['error' => 'Forbidden: missing permission'], 403);
            }
        }

        return $next($request);
    }
}
