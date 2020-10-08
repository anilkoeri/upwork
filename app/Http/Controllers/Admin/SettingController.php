<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SettingRequest;
use App\Http\Services\AmenityService;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SettingController extends Controller
{
    private $service;
    public function __construct()
    {
        $this->service = new AmenityService();
        \View::share('page_title', 'Setting');
    }
    /**
     * Display a listing of the resource.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
//        $this->authorize('view', Setting::class);
        return view('admin.setting.index');
    }


    /**
     * Show the form for creating a new resource.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
//        $this->authorize('create', Setting::class);
        $setting = new Setting();
        return view('admin.setting.create',compact('setting'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @param  SettingRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(SettingRequest $request)
    {
//        $this->authorize('create', Setting::class);

        $request->validated();


        $request->slug = strtolower(str_replace(" ","-",$request->name));

        Setting::create(
            [
                'name' => $request->name,
                'slug' => $request->slug,
                'value' => $request->value,
                'reserved' => '0'
            ]);

        return redirect('admin/setting')->with('success','Setting Created Successfully');

    }

    /**
     * Display the specified resource.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
//        $this->authorize('view', Setting::class);
        $setting = Setting::findOrFail($id);
        return view('admin.setting.show',compact('setting'));
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
//        $this->authorize('update', Setting::class);
        $setting = Setting::findOrFail($id);
        return view('admin.setting.edit',compact('setting'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @param  SettingRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(SettingRequest $request, $id)
    {
//        $this->authorize('update', Setting::class);
        $request->validated();

        $request->slug = strtolower(str_replace(" ","-",$request->name));

        Setting::where('id',$id)->update([
            'name'=>$request->name,
            'slug'=>$request->slug,
            'value'=>$request->value
        ]);
        return redirect('admin/setting')->with('success','Successfully Updated');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
//        $this->authorize('destroy', Setting::class);
        $setting = Setting::findOrFail($id);
        if($setting->reserved == 1){
            return redirect('admin/setting')->with('error','Reserved value cannot be deleted');
        }
        $setting->delete();

        return redirect('admin/setting')->with('success','Successfully Deleted');
    }
    /**
     * Return limited lists in json Format.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @param  \Illuminate\Http\Request  $request
     * @return string JSON
     */
    public function listSettings(Request $request)
    {
//        $this->authorize('view', Setting::class);
        $columns = array(
            0 => 'name',
            1 => 'value',
            2 => 'action'
        );
        $totalData = Setting::count();
        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        if($request->input('search.value')){
            $search = $request->input('search.value');
            $lists = Setting::where('name', 'like', "{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Setting::where('name', 'like', "{$search}%")
                ->count();
        }else{
            $lists = Setting::offset($start)
                ->limit($limit)
                ->orderBy($order,$dir)
                ->get();
            $totalFiltered = Setting::count();
        }

        $data = array();

        if($lists){
            foreach($lists as $r){
                $nestedData['name'] = $r->name;
                $nestedData['value'] = $r->value;
                $nestedData['action'] = \View::make('admin.setting.action')->with('r',$r)->render();

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
    }

    public function personal(Request $request)
    {
        $user = User::find(\Auth::user()->id);
        if($request->email_notification){
            $user->email_notification = 'on';
        }else{
            $user->email_notification = 'off';
        }
        $user->save();
        return response()->json([
            'sts' => '1'
        ],200);
    }
}
