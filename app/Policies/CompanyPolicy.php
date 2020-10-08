<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyPolicy
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
     * @param \App\Models\User $user
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
     * @param \App\Models\User $user
     * @return mixed
     */
    public function view(User $user)
    {
        $permission = Permission::where('name', 'company-view')->first();
        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can create the model.
     *
     * @param \App\Models\User $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permission = Permission::where('name', 'company-create')->first();
        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param \App\Models\User $user
     * @return mixed
     */
    public function update(User $user)
    {
        $permission = Permission::where('name', 'company-update')->first();
        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param \App\Models\User $user
     * @return mixed
     */
    public function destroy(User $user)
    {
        $permission = Permission::where('name', 'company-destroy')->first();
        return $user->hasRole($permission->roles);
    }

    public function manage(User $user, $id)
    {
        if($user->company_id == $id && $user->hasRole('companyAdmin')) {
            return true;
        }
    }
}
