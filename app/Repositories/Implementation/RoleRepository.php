<?php

namespace  App\Repositories\Implementation;

use App\Models\User;
use App\Repositories\Generic\GenericImplementation\GenericRepository;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleRepository extends GenericRepository
{

        public function getAllRoles(){
            return  Role::all();
        }
    public function revokePermissionTo(Role $role, string $permissions) {
        $role->revokePermissionTo($permissions);
    }

    public function givePermissionTo(Role $role, array $permissions) {
        $role->givePermissionTo($permissions);
    }

    public function hasRole(User $user, $roles = []) {
        return $user->hasRole($roles);
    }

    public function hasAnyRole(User $user, $roles = []) {
        return $user->hasAnyRole($roles);
    }

    public function hasAllRoles(User $user, $roles = []) {
        return $user->hasAllRoles($roles);
    }
    public function getRoles(User $user){
        return $user->getRoleNames();
    }
    public function model()
    {
        return 'Spatie\Permission\Models\Role';
    }

}
