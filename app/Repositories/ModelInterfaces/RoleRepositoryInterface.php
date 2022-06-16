<?php

namespace App\Repository\ModelInterfaces;

use App\Models\User;
use App\Repositories\Generic\GenericInterface\GenericRepositoryInterface;
use Spatie\Permission\Models\Role;

interface RoleRepositoryInterface extends GenericRepositoryInterface
{
    
    public function revokePermissionTo(Role $role, string $permissions);

    public function givePermissionTo(Role $role, array $permissions);

    public function hasRole(User $user, $roles = []);

    public function hasAnyRole(User $user, $roles = []);

    public function hasAllRoles(User $user, $roles = []);
}