<?php

namespace App\Rules;

use App\Models\User;
use Illuminate\Contracts\Validation\Rule;

class UniqueVerifiedEmail implements Rule
{
    protected $user_id;

    /**
     * UniqueVerifiedEmail constructor.
     * @param int $user_id
     */
    public function __construct($user_id = 0)
    {
        $this->user_id = $user_id;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $user = User::where('email',$value)
            ->whereNotNull('email_verified_at')
            ->first();
        if($user){
            if($user->id !==  $this->user_id){
                return true;
            }
            return false;
        }else{
            return true;
        }

    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Email is already registered.';
    }
}
