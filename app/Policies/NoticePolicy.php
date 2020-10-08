<?php

namespace App\Policies;

use App\Models\Notice;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class NoticePolicy
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

    }

    /**
     * Check either user can view or destroy
     *
     * @param User $user
     * @param Notice $notice
     * @return bool
     */
    public function manage(User $user, Notice $notice){
        return $user->id === $notice->user_id;
    }


}
