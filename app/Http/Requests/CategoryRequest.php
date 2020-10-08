<?php

namespace App\Http\Requests;

use App\Rules\UniqueCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class CategoryRequest extends FormRequest
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
     * @param Request $request
     * @return array
     */
    public function rules(Request $request)
    {
        return [
            'category_name' => 'required|string|unique:categories,category_name,null,null,company_id,'.$request->company_id,
            'company_id' => 'required|integer',
        ];

    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'category_name.required' => 'Category Name is required',
            'category_name.unique' => 'Category Name for this property is already used',
            'property_id.required' => 'Property Name is required',
        ];
    }
}
