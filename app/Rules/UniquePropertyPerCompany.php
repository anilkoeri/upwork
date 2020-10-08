<?php

namespace App\Rules;

use App\Models\Property;
use Illuminate\Contracts\Validation\Rule;

class UniquePropertyPerCompany implements Rule
{
    protected $company_id, $id;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($company_id,$id = 0)
    {
        $this->company_id = $company_id;
        $this->id = $id;
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
        $property = Property::where('property_name',$value)
            ->where('company_id',$this->company_id)
            ->first();
        if($property)
        {
            if($property->id == $this->id){
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
        return 'The Property Name is already used.';
    }
}
