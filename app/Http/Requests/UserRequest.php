<?php

namespace App\Http\Requests;

use App\Rules\CompanyRequiredRule;
use App\Rules\UniqueVerifiedEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class UserRequest extends FormRequest
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
    public function rules(Request $request)
    {
        $user_id = $this->route('user');
        if ($this->isMethod('POST')) {
            return [
                'name' => 'required|string',
                'email' => ['required','email',new UniqueVerifiedEmail()],
                'password' => 'required|min:8|confirmed',
                'password_confirmation' => 'required',
                'role_id' => 'required',
                'company_id' => [new CompanyRequiredRule($request->role_id)]
            ];
        }else{
            return [
                'name' => 'required|string',
                'email' => 'required|email|unique:users,email,'.$user_id,
                'role_id' => 'required',
                'company_id' => [new CompanyRequiredRule($request->role_id)]
            ];
        }

    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'email.unique' => 'Email is already registered.',
        ];
    }
}
