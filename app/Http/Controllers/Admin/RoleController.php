<?php

namespace App\Http\Controllers\Admin;

use App\Http\Services\AmenityService;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class RoleController extends Controller
{
    private $service;
    public function __construct()
    {
        $this->middleware('superAdmin');
        $this->service = new AmenityService();
        \View::share('page_title', 'Role');
    }

    public function index()
    {
        $roles = Role::all();
        return view('admin.role.index',compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $role = new Role();
        return view('admin.role.create',compact('role'));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(Request $request)
    {

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit($id)
    {
        $role = Role::find($id);
        $role_permission = \DB::table('permission_role')->where('role_id',$id)->pluck('permission_id')->toArray();
        $permissions = Permission::all();
        $search_arr = [' View',' Create', ' Update', ' Destroy'];
        $replace_arr = ['','', '', ''];
        $permissions->each(function ($val, $key) use($search_arr,$replace_arr) {
            return $val->labelGroup = str_replace($search_arr,$replace_arr,$val->label);
        });
        $permissions = $permissions->groupBy('labelGroup')->toArray();
        return view('admin.role.edit',compact('role','permissions','role_permission'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Role $role
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function update(Request $request,Role $role)
    {
        $role->permissions()->sync($request->permissions);

        return redirect('admin/role/'.$role->id.'/edit')->with('success','Successfully Updated');
    }



}
