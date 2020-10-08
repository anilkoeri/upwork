<?php

namespace App\Http\Controllers\Frontend;

use App\Models\InvitedUser;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;


class ActivateUserController extends Controller
{
    public function getActivateUser($unique_code)
    {

        $invited_user = InvitedUser::where('unique_code',$unique_code)->first();
        if($invited_user){
            \Auth::logout();
//            \Session::flush();
            return view('frontend.activate_user.index',compact('invited_user'));
        }else{
            abort('404');
        }
    }

    public function postActivateUser(Request $request,$unique_code)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'password_confirmation' => 'required',
        ]);
        $invited_user = InvitedUser::where('unique_code',$unique_code)->first();
        if($invited_user){
            $user = new User();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->password = Hash::make($request->password);
            $user->company_id = $request->company_id;
            $user->role_id = $request->role_id;
            $user->save();
            $invited_user->delete();
            return redirect('login')->with('success','Your account has been activated. Please log in.');
        }else{
            return redirect('login')->with('errors','The Link is expired or incorrect');
        }
    }
}
