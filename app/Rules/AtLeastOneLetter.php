<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class AtLeastOneLetter implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
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
        foreach($value as $vk => $v){
            if(preg_match('/[a-z]/i', $v)) {
                return true;
            } else {
                pe($v);
                return false;
            }
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The Category Name must contain at least one Alphabetic letter';
    }
}
