<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function getChangePassword()
    {
        $user = \Auth::user();
        return view('admin.profile.change_password',compact('user'));
    }

    public function postChangePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string|min:8|max:191',
            'password' => 'required|string|min:8|max:191|confirmed',
            'password_confirmation' => 'required|string|min:8|max:191',
        ]);
        $auth_user = \Auth::user();
        if(Hash::check($request->current_password, $auth_user->password))
        {
            $auth_user->password = Hash::make($request->password);
            $auth_user->save();
            return redirect()->route('profile.getChangePassword')
                ->with('success','Password Updated Successfully');
        }else{
            throw ValidationException::withMessages(['current_password' => 'Current password is incorrect']);
        }
    }
}
