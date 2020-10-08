<?php

namespace App\Http\Controllers\Admin;

use App\Http\Services\AmenityService;
use App\Models\Amenity;
use App\Models\AmenityCategoryMapping;
use App\Models\Category;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class AmenityCategoryMappingController extends Controller
{
    private $table,$service,$model;
    public function __construct()
    {
        $this->middleware('superAdmin')->only(['index','list','create']);
        $this->service = new AmenityService();
        $this->table = 'categories';
        $this->model = new Category();
        \View::share('page_title', 'Amenity Category Mapping');
    }

    public function index()
    {
        $categories = Category::whereNull('property_id')->whereNull('company_id')->get();
        return view('admin.amenity_category_mapping.index',compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'amenity_name' => 'required|max:191',
            'category' => 'required',
        ]);
        $acm = AmenityCategoryMapping::where('amenity_name',$request->amenity_name)
                                ->whereNull('company_id')
                                ->first();
        if($acm){
            return response()->json([
                'errors'    => [
                    'amenity_name' => [
                        'Amenity Name already registered'
                    ]
                ]
            ],422);
        }
        $a = new AmenityCategoryMapping();
        $a->amenity_name    = strtolower($request->amenity_name);
        $a->category_id     = $request->category;
        $a->property_id     = NULL;
        $a->company_id      = NULL;
        $a->created_at      = date('Y-m-d H:i:s');
        $a->updated_at      = date('Y-m-d H:i:s');
        $a->save();

        $action = 'action';

        return response()->json([
            'acm'           => $a,
            'category'      => $a->category,
            'action'        => $action
        ],200);
    }

//    public function edit($id){
//        $acm = AmenityCategoryMapping::findOrFail($id);
//        return view('admin.amenity_category_mapping.edit',compact($acm));
//    }

    public function editBulk($property_id)
    {
        $property = Property::findOrFail($property_id);
        if(!\Auth::user()->hasRole('superAdmin')){
            if(\Auth::user()->company_id != $property->company_id){
                abort('403');
            }
        }

        $categories = Category::where('property_id',$property_id)->orWhere('global','1')->get();
        $amenityCategoryMappings = AmenityCategoryMapping::with(['category','property'])
//            ->where('company_id',$property->company_id)
            ->where('property_id',$property->id)
            ->orWhereNull('property_id')
//            ->orderByRaw('LENGTH(amenity_name)', 'ASC')
            ->orderBy('property_id','desc')
            ->get();
        $amenities = Amenity::where('property_id',$property->id)->distinct('amenity_name')->pluck('amenity_name')->toArray();
        $amenities = array_map('strtolower', $amenities);
        $property_specific_amenities_array = array();
        foreach($amenityCategoryMappings as $ack => $acv){
            if(empty($acv->property_id)){
                if($acv->amenity_name == '' || !in_array($acv->amenity_name,$amenities) || in_array($acv->amenity_name,$property_specific_amenities_array)){
                    unset($amenityCategoryMappings[$ack]);
                }
            }else{
                $property_specific_amenities_array[] = $acv->amenity_name;
            }
        }
        $amenityCategoryMappings = $amenityCategoryMappings->sortBy('amenity_name',SORT_NATURAL|SORT_FLAG_CASE  );
        $auth_user = \Auth::user();
        return view('admin.amenity_category_mapping.edit',compact('amenityCategoryMappings','categories','property','auth_user'));
    }

    public function updateByCompany(Request $request, $company_id)
    {
        pe($request->all());
    }

    public function update(Request $request,$id)
    {
        $acm = AmenityCategoryMapping::find($id);
        if($request->update_global){
            //if it is requested to update globally update amenity category mapping (only for global mappping)
            $acm->category_id = $request->cat_id;
            $acm->updated_at = date('Y-m-d H:i:s');
            $acm->save();
            //finally also update the category of that amenity for that property
            $amenity = Amenity::where('amenity_name',$acm->amenity_name)
//                ->where('property_id',$request->property_id)
                ->whereNull('deleted_at')
                ->update(['category_id' => $request->cat_id]);
        }else{
            //if it not requsted to update globally
            //check if there is already a global mapping existed or not for desired mapping
            $acm_global = AmenityCategoryMapping::where('amenity_name',$acm->amenity_name)
                ->where('category_id',$request->cat_id)
                ->whereNull('property_id')
                ->first();
            if($acm_global){
                //if there is desired mapping already in global level
                //delete property level mapping so that it may fall back and use global level mapping
                //as if it doesn't find property level -> uses global level
                $acm->delete();
                $acm = $acm_global;
            }else{
                //update or create new mapping if there is not global mapping for the desired one
                $property = Property::findOrFail($request->property_id);
                $acm = AmenityCategoryMapping::withTrashed()->updateOrCreate(
                    [
                        'amenity_name' => strtolower($acm->amenity_name),
                        'property_id' => $request->property_id
                    ],
                    [
                        'company_id'    => $property->company_id,
                        'category_id'   => $request->cat_id,
                        'updated_at'    => date('Y-m-d H:i:s'),
                        'deleted_at'    => NULL
                    ]
                );
            }
            //finally also update the category of that amenity for that property
            $amenity = Amenity::where('amenity_name',$acm->amenity_name)
                ->where('property_id',$request->property_id)
                ->whereNull('deleted_at')
                ->update(['category_id' => $request->cat_id]);
        }

        return response()->json([
            'sts' => '1',
            'acm_id' => $acm->id,
        ],200);
    }

//    public function updateGlobal(Request $request,$id)
//    {
//        $acm = AmenityCategoryMapping::find($id);
//        if($request->update_global){
//            $acm->category_id = $request->cat_id;
//            $acm->save();
//        }else{
//            $acm_old = AmenityCategoryMapping::where(function($q)use($request,$acm){
//                    $q->where('amenity_name',$acm->amenity_name)
//                        ->whereNull('property_id')
//                        ->where('category_id',$request->cat_id);
//                })
//                ->orWhere(function($q)use($request,$acm){
//                    $q->where('amenity_name',$acm->amenity_name)->where('property_id', $request->property_id);
//                })
//                ->pluck('amenity_name');
//            if(!$acm_old->isEmpty()){
//                if(!empty($acm->property_id)){
//                    $acm_old->category_id = $request->cat_id;
//                    $acm_old->save();
//                }
//                $acm = $acm_old;
//            }else{
//                $acm = AmenityCategoryMapping::create([
//                    'amenity_name' => $acm->amenity_name,
//                    'category_id' => $request->cat_id,
//                    'property_id' => $request->property_id
//                ]);
//            }
//
//        }
//        return response()->json([
//            'sts' => '1',
//            'acm_id' => $acm->id
//        ],200);
//    }

    public function destroy($id)
    {
        $acm = AmenityCategoryMapping::find($id);
        $global_acm = AmenityCategoryMapping::where('amenity_name',$acm->amenity_name)
            ->whereNull('property_id')
            ->first();
        Amenity::where('amenity_name',$acm->amenity_name)
            ->where('property_id',$acm->property_id)
            ->whereNull('deleted_at')
            ->update(['category_id' => $global_acm->category_id]);
        $acm->forceDelete();
        return response()->json([
            'sts' => '1'
        ],200);
    }

    /**
     * Return data for the table.
     * @param Request $request
     */
    public function list(Request $request)
    {
        $columns = array(
            0 => 'amenity_name',
            1 => 'category_name',
            2 => 'action',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = !empty($columns[$request->input('order.0.column')])?$columns[$request->input('order.0.column')]:$columns[0];
        $dir = !empty($request->input('order.0.dir'))?$request->input('order.0.dir'):'desc';


        $query = DB::table('amenity_category_mapping as acm')
            ->leftJoin('categories as c', 'c.id', '=', 'acm.category_id')
            ->select('acm.*','c.category_name')
            ->whereNull('acm.company_id')
            ->whereNull('acm.deleted_at');
//            ->whereNotNull('users.email_verified_at');

        $name_search = $request->columns[0]['search']['value'];
        if(!empty($name_search)){
            $query->where('acm.amenity_name','like',$name_search.'%');
        }
        $category_search = $request->columns[1]['search']['value'];
        if(!empty($category_search)){
            $query->where('c.category_name','like',$category_search.'%');
        }

        $totalData = $query->count();

        $records = $query->orderBy($order,$dir)->skip($start)->take($limit)->get();
        $totalFiltered = $totalData;

        $data = array();
        if($records){
            foreach($records as $r){
                $nestedData['amenity_name'] = $r->amenity_name;
                $nestedData['category_name'] = $r->category_name;
                $nestedData['action'] = \View::make('admin.amenity_category_mapping.action')->with('r',$r)->render();
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
