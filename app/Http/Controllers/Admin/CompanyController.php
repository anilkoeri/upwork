<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\GenerateRandomString;
use App\Http\Requests\CompanyRequest;
use App\Mail\UserInvited;
use App\Models\Company;
use App\Http\Services\AmenityService;
use App\Models\InvitedUser;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use DB;
use Illuminate\Support\Facades\Hash;

class CompanyController extends Controller
{
    private $service,$generateRandomString;
    public function __construct()
    {
        $this->service = new AmenityService();
        $this->generateRandomString = new GenerateRandomString();
        \View::share('page_title', 'Company');
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index()
    {
        $this->authorize('view', Company::class);
        return view('admin.company.index');
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function create()
    {
        $this->authorize('create', Company::class);
        $company = new Company();
        return view('admin.company.create',compact('company'));
    }

    /**
     * Store a newly created resource in storage.
     * @param CompanyRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(CompanyRequest $request)
    {
        $this->authorize('create', Company::class);
        $request->validated();
        if (\Auth::user()->hasRole('superAdmin')){
            $apr_subscription = ($request->apr_subscription == 'yes') ? 'yes' : 'no';
        }else{
            $apr_subscription = 'no';
        }
        $company = Company::create(
            [
                'name'              => $request->name,
                'apr_subscription'  => $apr_subscription,
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s')
            ]);

        $apr_subscription = ($company->apr_subscription === 'yes')?'fa-check-circle text-check':'fa-times text-danger';
        $apr_subscription_text = "<i class='apr-status fa ".$apr_subscription."' aria-hidden='true'></i>";

        return response()->json([
            'sts'               => '1',
            'name'              => $company->name,
            'apr_subscription'  => $apr_subscription_text,
            'action'            => \View::make('admin.company.action')->with('r',$company)->render()
        ]);

    }

    /**
     * Display the specified resource.
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show($id)
    {
        $this->authorize('view', Company::class);
        $company = Company::findOrFail($id);
        return view('admin.company.show',compact('company'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function edit($id)
    {
        $this->authorize('update', Company::class);
        $company = Company::findOrFail($id);
        return view('admin.company.edit',compact('company'));
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(CompanyRequest $request, $id)
    {
        $this->authorize('update', Company::class);
        $request->validated();

        $company = Company::find($id);
        $company->name = $request->name;
        if (\Auth::user()->hasRole('superAdmin')) {
            $apr_subscription = ($request->apr_subscription == 'yes') ? 'yes' : 'no';
            $company->apr_subscription = $apr_subscription;
        }
        $company->updated_at = $request->name;
        $company->save();

        return redirect('admin/company')->with('success','Successfully updated');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->authorize('destroy', Company::class);
        $company = Company::findOrFail($id);
        $company->delete();

        return redirect('admin/company')->with('success','Successfully deleted');
    }

    public function list(Request $request)
    {
//        pe($request->all());
        $this->authorize('view', Company::class);
        $columns = array(
            0 => 'name',
            1 => 'apr_subscription',
            2 => 'action',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = !empty($columns[$request->input('order.0.column')])?$columns[$request->input('order.0.column')]:$columns[3];
        $dir = !empty($request->input('order.0.dir'))?$request->input('order.0.dir'):'desc';


        $query = DB::table('companies')
            ->select('companies.*');
        $auth_user = \Auth::user();
        if(!$auth_user->hasRole('superAdmin')){
            $query->where('id',$auth_user->company_id);
        }

        $totalData = $query->count();

        $records = $query->orderBy($order,$dir)->skip($start)->take($limit)->get();
        $totalFiltered = $totalData;

        $data = array();
        if($records){
            foreach($records as $r){
                $nestedData['name'] = $r->name;
                $apr_subscription = ($r->apr_subscription === 'yes')?'fa-check-circle text-check':'fa-times text-danger';
                $nestedData['apr_subscription'] = "<i class='apr-status fa ".$apr_subscription."' aria-hidden='true'></i>";
                $nestedData['action'] = \View::make('admin.company.action')->with('r',$r)->render();
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

    public function manageUsers($id)
    {
        $this->authorize('manage', [Company::class, $id]);
        $company = Company::findOrFail($id);
        return view('admin.company.user.index',compact('company'));
    }

    public function listUsers(Request $request,$id)
    {
//        pe($request->all());
        $this->authorize('manage', [Company::class, $id]);
        $columns = array(
            0 => 'name',
            1 => 'email',
            2 => 'role_id',
            3 => 'status',
            4 => 'created_at',
            5 => 'action',
//            5 => 'action',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = !empty($columns[$request->input('order.0.column')])?$columns[$request->input('order.0.column')]:$columns[2];
        $dir = !empty($request->input('order.0.dir'))?$request->input('order.0.dir'):'desc';


        $query = DB::table('users')
            ->select('users.id', 'users.name', 'users.email', 'users.role_id', 'users.created_at','users.email_verified_at','users.company_id')
            ->where('company_id',$id)
            ->where('role_id','<>','1')
            ->whereNull('deleted_at');

        $username_search = $request->columns[0]['search']['value'];
        if(!empty($username_search)){
            $query->where('users.name','like','%'.$username_search.'%');
        }
        $useremail_search = $request->columns[1]['search']['value'];
        if(!empty($useremail_search)){
            $query->where('users.email','like','%'.$useremail_search.'%');
        }

        $role = $request->columns[2]['search']['value'];
        if(!empty($role)){
            $query->where('users.role_id',$role);
        }
        $status = $request->columns[3]['search']['value'];
        if(!empty($status)){
            if($status === '1'){
                $query->whereNotNull('users.email_verified_at');
            }else{
                $query->whereNull('users.email_verified_at');
            }
        }

        $totalData = $query->count();

        $records = $query->orderBy($order,$dir)->skip($start)->take($limit)->get();

        $totalFiltered = $totalData;

        $data = array();
        if($records){
            foreach($records as $r){
                if($r->role_id == 2){
                   $role_label = 'Company Admin';
                }elseif($r->role_id == 3){
                    $role_label = 'Company User';
                }else{
                    $role_label = '';
                }
                $nestedData['id'] = $r->id;
                $nestedData['name'] = $r->name;
                $nestedData['email'] = $r->email;
                $nestedData['role_id'] = $role_label;
                $nestedData['status'] = ($r->email_verified_at != NULL)?'Active':'Invited';
                $nestedData['created_at'] = $r->created_at;
                $nestedData['action'] = \View::make('admin.company.user.action')->with('r',$r)->render();
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

    public function deleteUsers(Request $request,$id,$user_id)
    {
        $this->authorize('manage', [Company::class, $id]);

        $auth = \Auth::user();
        User::where('id',$user_id)->forceDelete();
        return response()->json([
            'sts' => '1',
            'message' => 'Successfully Deleted'
        ],200);
//        if($request->has('id')) {
//            if ($auth->company_id == $id && $auth->hasRole('companyAdmin') || $auth->hasRole('superAdmin')) {
//                $users = User::whereIn('id', $request->id)->pluck('id');
//                User::whereIn('id', $users)
//                    ->update(['company_id' => NULL, 'role_id' => NULL]);
//            }
//            return back()->with('success','Successfully Updated User Lists');
//        }else{
//            return back()->with('error','Please select at least one user');
//        }
    }

    public function inviteUser(Request $request, $id)
    {
        $this->authorize('manage', [Company::class, $id]);
        $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191|unique:users,email',
            'role_id' => 'required'
        ]);

            $company = Company::findOrFail($id);
            $count = $company->properties->count();
            if($count){
                $first_time = '0';
            }else{
                $first_time = '1';
            }

            $user               = new User();
            $user->name         = $request->name;
            $user->email        = $request->email;
            $user->password     = Hash::make($request->password);
            $user->company_id   = $id;
            $user->role_id      = $request->role_id;
            $user->first_time   = $first_time;
            $user->save();
            $user->sendEmailVerificationNotification();

        return response()->json([
            'sts' => '1',
            'invited_user' => $user
        ],200);
    }

    public function invitedUsers($id)
    {
//        $this->authorize('manage', [Company::class, $id]);
        $company = Company::find($id);
        return view('admin.company.invited-user.index',compact('company'));
    }

    public function listInvitedUsers(Request $request,$id)
    {
//        pe($request->all());
//        $this->authorize('manage', [Company::class, $id]);
        $columns = array(
            0 => 'id',
            1 => 'name',
            2 => 'email',
            3 => 'role_id',
            4 => 'invited_at',
            5 => 'action',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = !empty($columns[$request->input('order.0.column')])?$columns[$request->input('order.0.column')]:$columns[2];
        $dir = !empty($request->input('order.0.dir'))?$request->input('order.0.dir'):'desc';


        $query = DB::table('invited_users as u')
            ->select('u.id', 'u.name', 'u.email', 'u.role_id', 'u.invited_at')
            ->where('u.company_id',$id)
            ->where('u.role_id','<>','1');

        $username_search = $request->columns[1]['search']['value'];
        if(!empty($username_search)){
            $query->where('u.name','like','%'.$username_search.'%');
        }
        $useremail_search = $request->columns[2]['search']['value'];
        if(!empty($useremail_search)){
            $query->where('u.email','like','%'.$useremail_search.'%');
        }

        $role = $request->columns[3]['search']['value'];
        if(!empty($role)){
            $query->where('u.role_id',$role);
        }

        $totalData = $query->count();

        $records = $query->orderBy($order,$dir)->skip($start)->take($limit)->get();
        $totalFiltered = $totalData;

        $data = array();
        if($records){
            foreach($records as $r){
                if($r->role_id == 2){
                    $role_label = 'Company Admin';
                }elseif($r->role_id == 3){
                    $role_label = 'Company User';
                }else{
                    $role_label = '';
                }
                $nestedData['id'] = $r->id;
                $nestedData['name'] = $r->name;
                $nestedData['email'] = $r->email;
                $nestedData['role_id'] = $role_label;
                $nestedData['invited_at'] = $r->invited_at;
                $nestedData['action'] = \View::make('admin.company.invited-user._action')->with('r',$r)->render();
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

    public function removeInvitedUsers(Request $request,$id)
    {
        $this->authorize('manage', [Company::class, $id]);
        $auth = \Auth::user();

        if($request->has('id')) {
            if ($auth->company_id == $id && $auth->hasRole('companyAdmin') || $auth->hasRole('superAdmin')) {
                $invited_users = InvitedUser::whereIn('id', $request->id)->pluck('id');
                InvitedUser::whereIn('id', $invited_users)
                    ->delete();
            }
            return back()->with('success','Successfully Removed');
        }else{
            return back()->with('error','Please select a user first');
        }
    }

    public function resendEmail($invited_user_id)
    {
        $invited_user = InvitedUser::findOrFail($invited_user_id);
        $arr_data = [
            'user_name' => $invited_user->name,
            'company_name' => $invited_user->company->name,
            'auth_name' => \Auth::user()->name,
            'activation_link' => $invited_user->unique_code
        ];
        \Mail::to($invited_user->email)->send(new UserInvited($arr_data));
        if (\Mail::failures()) {
            DB::rollback();
            return response()->json([
                'errors' => [
                    'email' => [
                        'Something went wrong, mail cannot be sent. Please try again.'
                    ]
                ]
            ],422);
        }
        return response()->json([
            'sts' => '1',
            'invited_user' => $invited_user
        ],200);
    }

}
