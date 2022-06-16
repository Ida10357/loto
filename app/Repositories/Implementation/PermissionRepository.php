<?php

namespace  App\Repositories\Implementation;

use App\Models\User;
use App\Repositories\Generic\GenericImplementation\GenericRepository;

class PermissionRepository extends GenericRepository
{
    
    public function hasPermissionTo(User $user, string $permission) {
        return $user->hasPermissionTo($permission);
    }
    
    public function hasAnyPermission(User $user, array $permission) {
        return $user->hasAnyPermission($permission);
    }

    public function hasAllPermissions(User $user, array $permission) {
        return $user->hasAllPermissions($permission);
    }

    public function model()
    {
        return 'Spatie\Permission\Models\Permission';
    }

}