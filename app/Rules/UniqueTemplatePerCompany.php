<?php

namespace App\Rules;

use App\Models\MappingTemplate;
use Illuminate\Contracts\Validation\Rule;

class UniqueTemplatePerCompany implements Rule
{
    protected $company_id;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($company_id)
    {
        $this->company_id = $company_id;
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
        $template = MappingTemplate::where('template_name',$value)
            ->where('company_id',$this->company_id)
            ->first();

        if($template)
        {
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
        return 'The Mapping Template Name is already used.';
    }
}
