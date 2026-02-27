<?php
// src/Auth/RoleManager.php
declare(strict_types=1);
namespace Smallwork\Auth;

class RoleManager
{
    public function __construct(private array $roles = []) {}

    public function hasPermission(string $role, string $permission): bool
    {
        return in_array($permission, $this->roles[$role] ?? [], true);
    }

    public function getPermissions(string $role): array
    {
        return $this->roles[$role] ?? [];
    }

    public function roleExists(string $role): bool
    {
        return isset($this->roles[$role]);
    }
}
