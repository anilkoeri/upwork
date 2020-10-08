<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\UserRequest;
use App\Http\Services\AmenityService;
use App\Models\Company;
use App\Models\InvitedUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use DB,Carbon\Carbon;

use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    private $table,$service,$model;
    public function __construct()
    {
        $this->middleware('superAdmin',['except' => 'disableWelcome']);
        $this->service = new AmenityService();
        $this->table = 'users';
        $this->model = new User();
        \View::share('page_title', 'User');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index()
    {
//        $this->authorize('view', User::class);
        return view('admin.user.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create()
    {
//        $this->authorize('create', User::class);
        $user = new User();
        $roles = Role::pluck('label','id');
        $companies = Company::all();
        return view('admin.user.create',compact('user','roles','companies'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param UserRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(UserRequest $request)
    {
        $this->authorize('create', User::class);

        if($request->ajax()){
//            $invited_user = InvitedUser::with('company')->where('email',$request->email)->first();
            $invited_user = User::with('company')->where('email',$request->email)->whereNull('email_verified_at')->first();
            if($invited_user){
                return response()->json([
                    'sts' => '-1',
                    'message' => 'The email address you\'re inviting is already invited by '.$invited_user->company->name.'. Do you still want to create this user?'
                ]);
            }else{
                return response()->json([
                    'sts' => '1',
                ]);
            }
        }else{
            if($request->role_id == 1){
                $company_id = NULL;
            }else{
                $company_id = $request->company_id;
            }

            $invited_user = User::with('company')->where('email',$request->email)->whereNull('email_verified_at')->first();

            if($invited_user){
                $user = User::findOrFail($invited_user->id);
            }else{
                $user = new User();
            }

            $user->name = $request->name;
            $user->email = $request->email;
            $user->password = Hash::make($request->password);
            $user->company_id = $company_id;
            $user->role_id = $request->role_id;
            $user->save();
            $user->sendEmailVerificationNotification();
//            InvitedUser::where('email',$request->email)->delete();

            return redirect('admin/user')->with('success','User Created Successfully, an activation email is sent to the registered email address');
        }

    }

    /**
     * Display the specified resource.
     *
     *@throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
//        $this->authorize('view', User::class);
        $user = User::findOrFail($id);
        return view('admin.user.show',compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
//        $this->authorize('update', User::class);
        $user = User::findOrFail($id);
        $roles = Role::pluck('label','id');
        $companies = Company::all();
        return view('admin.user.edit',compact('user','roles','companies'));
    }

    /**
     * Update the specified resource in storage.
     *
     *@throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @param  UserRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UserRequest $request, $id)
    {
//        $this->authorize('update', User::class);

        User::where('id',$id)->update([
            'name'=>$request->name,
            'email'=>$request->email,
            'company_id' => $request->company_id,
            'role_id' => $request->role_id
        ]);
        return redirect('admin/user')->with('success','Successfully Updated');
    }


    /**
     * Remove the specified resource from storage.
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy($id)
    {
//        $this->authorize('destroy', User::class);
        $user = User::findOrFail($id);
        $user->forceDelete();

        return redirect('admin/user')->with('success','Successfully Deleted');
    }

    /**
     * Return limited lists in json Format.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @param  \Illuminate\Http\Request  $request
     * @return string JSON
     */
    public function list(Request $request)
    {
//        $this->authorize('view', User::class);
        $columns = array(
            0 => 'name',
            1 => 'email',
            2 => 'company_name',
            3 => 'created_at',
            4 => 'action',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = !empty($columns[$request->input('order.0.column')])?$columns[$request->input('order.0.column')]:$columns[3];
        $dir = !empty($request->input('order.0.dir'))?$request->input('order.0.dir'):'desc';


        $query = DB::table('users')
            ->leftJoin('companies', 'companies.id', '=', 'users.company_id')
            ->select('users.*','companies.name as company_name')
            ->whereNull('users.deleted_at');
//            ->whereNotNull('users.email_verified_at');

        $name_search = $request->columns[0]['search']['value'];
        if(!empty($name_search)){
            $query->where('users.name','like',$name_search.'%');
        }
        $email_search = $request->columns[1]['search']['value'];
        if(!empty($email_search)){
            $query->where('users.email','like',$email_search.'%');
        }

        $company_search = $request->columns[2]['search']['value'];
        if(!empty($company_search)){
            $query->where('companies.name','like',$company_search.'%');
        }

        $totalData = $query->count();

        $records = $query->orderBy($order,$dir)->skip($start)->take($limit)->get();
        $totalFiltered = $totalData;

        $data = array();
        if($records){
            foreach($records as $r){
                $nestedData['name'] = $r->name;
                $nestedData['email'] = $r->email;
                $nestedData['company_name'] = $r->company_name;
                $nestedData['created_at'] = Carbon::parse($r->created_at)->format('d/m/Y');
                $nestedData['action'] = \View::make('admin.user.action')->with('r',$r)->render();
                $data[] = $nestedData;
            }
        }
        $json_data = array(
            "draw"          => intval($request->input('draw')),
            "recordsTotal"  => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"          => $data
        );
        echo json_encode($json_data);
        exit();

    }

    public function resendInvite($user_id)
    {
        $user = User::findOrFail($user_id);
        $user->sendEmailVerificationNotification();
        return response()->json([
            'sts' => '1',
            'message' => 'Email Sent Successfully'
        ],200);
    }

    public function disableWelcome()
    {
        $user               = \Auth::user();
        $user->first_time   =  '0';
        $user->save();
        return response()->json([
            'message'   => 'Success'
        ],200);
    }


}
