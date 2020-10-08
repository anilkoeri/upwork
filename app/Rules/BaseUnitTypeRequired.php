<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class BaseUnitTypeRequired implements Rule
{
    protected $count;

    /**
     * BaseUnitTypeRequired constructor.
     * @param $count
     */
    public function __construct($count)
    {
        $this->count = $count;
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
        if($this->count != count($value)){
            return false;
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
        return 'Please select a base unit type for each grouping, then proceed.';
    }
}
