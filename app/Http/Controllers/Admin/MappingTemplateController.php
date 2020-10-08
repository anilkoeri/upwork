<?php

namespace App\Http\Controllers\Admin;

use App\Http\Services\AmenityService;
use App\Models\MappingTemplate;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class MappingTemplateController extends Controller
{
    private $service;
    public function __construct()
    {
        $this->service = new AmenityService();
        \View::share('page_title', 'Mapping Template');
    }
    /**
     * Display a listing of the resource.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->authorize('view', MappingTemplate::class);
        return view('admin.mapping-template.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $mappingTemplate = MappingTemplate::findOrFail($id);
        $auth_user = \Auth::user();
        if(!$auth_user->hasRole('superAdmin')){
            if($mappingTemplate->company_id != $auth_user->company_id){
                abort('401');
            }
        }
        return view('admin.mapping-template.show',compact('mappingTemplate'));
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $mapping_template = MappingTemplate::find($id);
        $mapping_template->template_name = $mapping_template->property->property_name.'-'.date('Y_m_d_H_i_s');
        $mapping_template->saved = '0';
        $mapping_template->save();
        return response()->json([
            'message' => 'Successfully Deleted',
        ],200);
    }

    /**
     * @param Request $request
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function list(Request $request)
    {
        $this->authorize('view', MappingTemplate::class);
        $auth_user = \Auth::user();
        $columns = array(
            0 => 'template_name',
            1 => 'action',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = !empty($columns[$request->input('order.0.column')])?$columns[$request->input('order.0.column')]:$columns[3];
        $dir = !empty($request->input('order.0.dir'))?$request->input('order.0.dir'):'desc';


        $query = DB::table('mapping_templates')
            ->select('mapping_templates.*')
            ->where('saved','1');
        if(!$auth_user->hasRole('superAdmin')){
            $query->where('mapping_templates.company_id',$auth_user->company_id);
        }

        $template_name_search = $request->columns[0]['search']['value'];
        if(!empty($template_name_search)){
            $query->where('mapping_templates.template_name','like','%'.$template_name_search.'%');
        }

        $totalData = $query->count();

        $records = $query->orderBy($order,$dir)->skip($start)->take($limit)->get();
        $totalFiltered = $totalData;

        $data = array();
        if($records){
            foreach($records as $r){
                $nestedData['id'] = $r->id;
                $nestedData['template_name'] = $r->template_name;
                $nestedData['action'] = \View::make('admin.mapping-template.action')->with('r',$r)->render();
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
