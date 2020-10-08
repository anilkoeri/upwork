<?php

namespace App\Http\Requests;

use App\Rules\UniqueNonUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class NonUnitRequest extends FormRequest
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
     * @param Request $request
     * @return array
     */
    public function rules(Request $request)
    {
        $non_unit = $this->route('non_unit');
        return [
            'non_unit_number' => ['required','integer',new UniqueNonUnit($request->building_id, $non_unit)],
        ];
    }


}
