<?php

namespace App\Http\Controllers\Admin;

use App\Http\Services\AmenityService;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use DB,Auth;

class NoticeController extends Controller
{
    private $service;
    public function __construct()
    {
        $this->service = new AmenityService();
        \View::share('page_title', 'Notice');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.notice.index');
    }

    /**
     * @param $slug
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show($slug)
    {
//        $this->authorize('manage', $notice);
        $notice = Notice::where('slug',$slug)->firstOrFail();
        $notice->seen = 'y';
        $notice->save();
        return view('admin.notice.show',compact('notice'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @param  Notice $notice
     * @return \Illuminate\Http\Response
     */
    public function destroy(Notice $notice)
    {
        $this->authorize('manage', $notice);
//        $notice = Notice::findOrFail($id);
        $notice->delete();

        return redirect('admin/notice')->with('success','Successfully Deleted');
    }

    /**
     * Return limited lists in json Format.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string JSON
     */
    public function list(Request $request)
    {
        $columns = array(
            0 => 'title',
            1 => 'updated_at',
            2 => 'action',
        );

        $totalData = Notice::where('user_id',\Auth::user()->id)->count();
        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');


        $results = Notice::where('user_id',\Auth::user()->id)
            ->offset($start)
            ->limit($limit)
            ->orderBy($order,$dir)
            ->get();
        $totalFiltered = $totalData;

        $id_arr = array();
        $data = array();

        if($results){

            foreach($results as $r){
                $id_arr[] = $r->id;
                $nestedData['title'] = $r->title;
                $nestedData['updated_at'] = $r->updated_at;
                $nestedData['action'] = \View::make('admin.notice.action')->with('r',$r)->render();
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


}
