<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoryPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }
    /**
     * Run the function before other function
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function before(User $user)
    {
        if ($user->hasRole('superAdmin')) {
            return true;
        }
    }
    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function view(User $user)
    {
        $permission = Permission::where('name', 'category-view')->first();
        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can create the model.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permission = Permission::where('name', 'category-create')->first();
        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function update(User $user)
    {
        $permission = Permission::where('name', 'category-update')->first();
        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function destroy(User $user)
    {
        $permission = Permission::where('name', 'category-destroy')->first();
        return $user->hasRole($permission->roles);
    }
}
