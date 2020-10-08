<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class RequiredCSVColumn implements Rule
{
    protected $additional_field = [];
    protected $required_all;
    private $message;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($additional_field,$required_all,$message = NULL)
    {
        $this->additional_field = $additional_field;
        $this->required_all = $required_all;
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

        foreach ($this->required_all as $k => $v) {
            if(!in_array($v,$full_row))
            {
                $arr[] = $v;
                if($k != 0){
                    $this->message .= '</br>';
                }
                $v = str_replace("_"," ",$v);
                $this->message .= ucwords($v). ' is required';
            }
        }
        if(!empty($arr)){
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
        return $this->message;
    }
}
