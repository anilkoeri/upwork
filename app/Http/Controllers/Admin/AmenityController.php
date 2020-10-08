<?php

namespace App\Http\Controllers\Admin;

use App\Http\Services\AmenityService;
use App\Models\Amenity;
use App\Models\Category;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use DB;
use Rap2hpoutre\FastExcel\FastExcel;

class AmenityController extends Controller
{
    private $table,$service,$model;
    public function __construct()
    {
        $this->service = new AmenityService();
        $this->table = 'amenities';
        $this->model = new Amenity();
        \View::share('page_title', 'Amenity');
        \View::share('page_title', 'Amenity');
    }
    /**
     * Display a listing of the resource.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->authorize('view', Amenity::class);
        $categories = Category::get(['id','category_name']);
        return view('admin.amenity.index',compact('categories'));
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
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $this->authorize('view', Amenity::class);
        $amenity = Amenity::findOrFail($id);
        return view('admin.amenity.show',compact('amenity'));

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
        $this->authorize('update', Amenity::class);
        $auth_user = \Auth::user();
        $amenity = Amenity::findOrFail($id);
        $query = Category::with('company');
                            if(!$auth_user->hasRole('superAdmin')){
                                $query->where('global',1)
                                    ->orWhere('property_id',$auth_user->company->property_id);
                            }
        $categories = $query->get();

        return view('admin.amenity.edit',compact('amenity','categories'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->authorize('update', Amenity::class);

        Amenity::where('id',$id)->update([
            'amenity_name'=>$request->amenity_name,
            'category_id' => $request->category_id,
        ]);
        return redirect('admin/amenity')->with('success','Successfully Updated');
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
        $this->authorize('destroy', Amenity::class);

        $amenity = Amenity::findOrFail($id);
        $amenity->delete();
        return redirect('admin/amenity/')->with('success','Successfully Deleted');
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
        $this->authorize('view', Amenity::class);
        $columns = array(
            0 => 'amenities.amenity_name',
            1 => 'categories.category_name',
            2 => 'properties.property_name',
            3 => 'amenity_value',
            4 => 'amenity_values_count',
//            5 => 'action',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $query = Amenity::join('categories', 'categories.id', '=', 'amenities.category_id')
        ->leftJoin('properties', 'properties.id', '=', 'amenities.property_id')
        ->with(['amenityValues' => function($q) {
            $q->withCount('unitAmenityValues');
        }]);
//        ->whereNull('amenities.deleted_at');

        $auth_user = \Auth::user();
        if(!$auth_user->hasRole('superAdmin'))
        {
            $properties_ids = $auth_user->company->properties->pluck('id');
            $query->whereIn('amenities.property_id',$properties_ids);
        }

        $am_name_search = $request->columns[0]['search']['value'];
        if(!empty($am_name_search)){
            $query->where('amenities.amenity_name','like','%'.$am_name_search.'%');
        }
        $cat_name_search = $request->columns[1]['search']['value'];
        if(!empty($cat_name_search)){
            $query->where('categories.category_name','like','%'.$cat_name_search.'%');
        }
        $prop_name_search = $request->columns[2]['search']['value'];
        if(!empty($prop_name_search)){
            $query->where('properties.property_name','like','%'.$prop_name_search.'%');
        }

        $totalData = $query->count();

        $records = $query->skip($start)->take($limit)
            ->orderBy($order,$dir)
            ->get([
                'amenities.id','amenities.amenity_name','amenities.deleted_at',
                'categories.category_name',
                'properties.property_name'
            ]);

        $totalFiltered = $totalData;

        $data = array();
        if($records){
            foreach($records as $r){
                $temp_arr1 = $temp_arr2 = array();
                $nestedData['amenity_name'] = $r->amenity_name;
                $nestedData['category_name'] = $r->category_name;
                $nestedData['property_name'] = $r->property_name;
                $temp_arr1 = $r->amenityValues->pluck('amenity_value');
                $temp_arr2 = $r->amenityValues->pluck('unit_amenity_values_count');
                if($temp_arr1){
                    $nestedData['amenity_value'] = $temp_arr1->implode(', ');
                    $nestedData['amenity_values_count'] = $temp_arr2->implode(', ');
                }else{
                    $nestedData['amenity_value'] = 'N/A';
                    $nestedData['amenity_values_count'] = 'N/A';
                }
//                $nestedData['action'] = \View::make('admin.amenity.action')->with('r',$r)->render();
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
//        return response()->json([
//            $json_data
//        ],200);

    }

    public function export(Request $request)
    {
        $this->authorize('view', Amenity::class);
        $file_name = 'Benchmarking - All Company.xlsx';

        $query = Amenity::join('categories', 'categories.id', '=', 'amenities.category_id')
            ->leftJoin('properties', 'properties.id', '=', 'amenities.property_id')
            ->with(['amenityValues' => function($q) {
                $q->withCount('unitAmenityValues');
            }]);
        $auth_user = \Auth::user();
        if(!$auth_user->hasRole('superAdmin'))
        {
            $properties_ids = $auth_user->company->properties->pluck('id');
            $query->whereIn('amenities.property_id',$properties_ids);
            $file_name = 'Benchmarking - '.$auth_user->company->name.'.xlsx';
        }
        $records = $query->orderBy('amenities.amenity_name','asc')
                ->get([
                    'amenities.id','amenities.amenity_name','amenities.deleted_at',
                    'categories.category_name',
                    'properties.property_name'
                ]);
        $items = array();
        $export_data = $records->flatMap(function ($r) {
            $temp_arr1 = $temp_arr2 = array();
            $temp_arr1 = $r->amenityValues->pluck('amenity_value');
            $temp_arr2 = $r->amenityValues->pluck('unit_amenity_values_count');
            if($temp_arr1){
                $am_value = $temp_arr1->implode(', ');
                $am_count = $temp_arr2->implode(', ');
            }else{
                $am_value = 'N/A';
                $am_count = 'N/A';
            }
            $items[] =  [
                'Amenity Name' => $r->amenity_name,
                'Category Name' => $r->category_name,
                'Property Name' => $r->property_name,
                'Amenity Value' => $am_value,
                'Count' => $am_count
            ];
            return $items;
        });
        return (new FastExcel($export_data))->download($file_name);
    }
}
