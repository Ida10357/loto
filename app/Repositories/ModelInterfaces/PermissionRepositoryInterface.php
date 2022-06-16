<?php

namespace App\Repository\ModelInterfaces;

use App\Models\User;
use App\Repositories\Generic\GenericInterface\GenericRepositoryInterface;
use Spatie\Permission\Models\Role;

interface PermissionRepositoryInterface extends GenericRepositoryInterface
{ 
    
    public function hasPermissionTo(User $user, string $permission);
    
    public function hasAnyPermission(User $user, array $permission);

    public function hasAllPermissions(User $user, array $permission);
}