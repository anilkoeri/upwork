<?php

namespace App\Rules;

use App\Models\NonUnit;
use Illuminate\Contracts\Validation\Rule;

class UniqueNonUnit implements Rule
{
    protected $building_id,$non_unit;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($building_id,$non_unit)
    {
        $this->building_id = $building_id;
        $this->non_unit = $non_unit;
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
        $nonUnit = NonUnit::where('non_unit_number',$value)
                ->where('building_id',$this->building_id)
                ->first();

        if($nonUnit)
        {
            if($nonUnit->id == $this->non_unit){
                return true;
            }else{
                return false;
            }

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
        return 'The unit number from this building has already been set as non-unit.';
    }
}
