<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class AdditionalFieldValidate implements Rule
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
        foreach($value as $k => $v){
                if(array_filter($v)){
                   foreach($v as $vv){
                       if(empty($vv)){
                           return false;
                       }
                   }
                }
        }
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'CSV Header, Database Column and Sample Value all are required for each additional row';
    }
}
