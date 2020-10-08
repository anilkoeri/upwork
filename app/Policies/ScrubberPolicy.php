<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ScrubberPolicy
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
    public function scrubberView(User $user)
    {
        $permission = Permission::where('name', 'scrubber-view')->first();
        return $user->hasRole($permission->roles);
    }

}
