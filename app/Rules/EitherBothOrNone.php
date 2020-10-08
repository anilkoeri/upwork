<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class EitherBothOrNone implements Rule
{
    protected $additional_field,$arr_data;
    private $message;

    /**
     * EitherBothOrNone constructor.
     * @param $arr_data
     */
    public function __construct($additional_field,$arr_data,$message = NULL)
    {
        $this->additional_field = $additional_field;
        $this->arr_data = $arr_data;
        $this->message =  $message;
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
        $additional_rows = $arr = array();
        foreach($this->additional_field as $ak => $av){
            $additional_rows[] = $av['row'];
        }
        $full_row = array_merge($value,$additional_rows);

        $result = array_intersect($this->arr_data, $full_row);

        $count = count($result);
        if($count == 0 || $count == count($this->arr_data)){
            return true;
        }else{
            $this->message = "Floor and Stack are atomic. Either need to pass both or need to remove both.";
            return false;
        }

    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }
}
