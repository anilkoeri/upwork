<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class CompanyRequiredRule implements Rule
{
    protected $role_id;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($role_id)
    {
        $this->role_id = $role_id;
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
        if(!empty($value) || $this->role_id == '1'){
            return true;
        }
        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Company Is Required if the new user is not the SuperAdmin.';
    }
}
