<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnitAmenityValueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'amenity_value' => 'required|numeric',
//            'effective_date' => 'required|date',
//            'inactive_date' => 'required|date',
        ];
    }
}
