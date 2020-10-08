<?php

namespace App\Http\Controllers\Admin;

use App\Http\Services\AmenityService;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use DB;

class EmailTemplateController extends Controller
{
    private $service,$auth_user;
    public function __construct()
    {
        $this->middleware('superAdmin');
        $this->service = new AmenityService();
        \View::share('page_title', 'Email Template');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        return view('admin.email-template.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
//        $emailTemplate = new EmailTemplate();
//        return view('admin.email-template.create',compact('emailTemplate'));
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
        $emailTemplate = EmailTemplate::findOrFail($id);
        return view('admin.email-template.show',compact('emailTemplate'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $emailTemplate = EmailTemplate::findOrFail($id);
        return view('admin.email-template.edit',compact('emailTemplate'));
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
        $request->validate([
            'body' => 'required',
        ]);

        EmailTemplate::where('id',$id)->update([
            'body'=>$request->body,
        ]);
        return redirect('admin/email-template')->with('success','Successfully Updated');
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

    /**
     * @param Request $request
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function list(Request $request)
    {
        $columns = array(
            0 => 'title',
            1 => 'action',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = !empty($columns[$request->input('order.0.column')])?$columns[$request->input('order.0.column')]:$columns[3];
        $dir = !empty($request->input('order.0.dir'))?$request->input('order.0.dir'):'desc';


        $query = DB::table('email_templates')
            ->select('email_templates.*');

        $title_search = $request->columns[0]['search']['value'];
        if(!empty($title_search)){
            $query->where('email_templates.title','like','%'.$title_search.'%');
        }

        $totalData = $query->count();

        $records = $query->orderBy($order,$dir)->skip($start)->take($limit)->get();
        $totalFiltered = $totalData;

        $data = array();
        if($records){
            foreach($records as $r){
                $nestedData['title'] = $r->title;
                $nestedData['action'] = \View::make('admin.email-template.action')->with('r',$r)->render();
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
