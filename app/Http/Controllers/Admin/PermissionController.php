<?php

namespace App\Http\Controllers\Admin;

use App\Http\Services\AmenityService;
use App\Models\Permission;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PermissionController extends Controller
{
    private $service;
    public function __construct()
    {
        $this->service = new AmenityService();
        \View::share('page_title', 'Permission');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $permissions = Permission::all();
        $search_arr = [' View',' Create', ' Update', ' Destroy'];
        $replace_arr = ['','', '', ''];
        $permissions->each(function ($permission, $key) use($search_arr,$replace_arr) {
            $permission->cat_name =str_replace($search_arr, $replace_arr, $permission->label);
            return $permission;
        });
        $grouped_data = $permissions->groupBy('cat_name')->toArray();
        return view('admin.permission.index',compact('permissions','grouped_data'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
