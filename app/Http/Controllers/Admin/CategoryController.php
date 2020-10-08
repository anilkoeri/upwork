<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CategoryRequest;
use App\Http\Services\AmenityService;
use App\Models\Amenity;
use App\Models\AmenityCategoryMapping;
use App\Models\Category;
use App\Models\Company;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class CategoryController extends Controller
{
    private $table,$service,$model;
    public function __construct()
    {
        $this->service = new AmenityService();
        $this->table = 'categories';
        $this->model = new Category();
        \View::share('page_title', 'Category');
    }

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index(Request $request)
    {
        $this->authorize('view', Category::class);

        if($request->property){
            $property = Property::findOrFail($request->property);
        }else{
            $property = '0';
        }
        return view('admin.category.index',compact('property'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->authorize('create', Category::class);
        $category = new Category();
        $companies = Company::all();
        $properties = Property::get(['id','property_name']);
        return view('admin.category.create',compact('category','properties','companies'));
    }

    /**
     * Store a newly created resource in storage.
     * @param CategoryRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(CategoryRequest $request)
    {
        $this->authorize('create', Category::class);
        Category::create(
            [
                'category_name' => trim($request->category_name),
//                'property_id' => $request->property_id,
                'company_id' => $request->company_id,
            ]);
        if($request->ajax()){
            return response()->json([
                'sts' => '1'
            ],200);
        }else{
            return redirect('admin/category')->with('success','Successfully Created');
        }
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
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function edit($id)
    {
        $this->authorize('update', Category::class);
        $category = Category::findOrFail($id);
        $companies = Company::all();
        $global_categories = Category::where('global',1)->get();
        $properties = Property::get(['id','property_name']);
        return view('admin.category.edit',compact('category','global_categories','properties','companies'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @param  CategoryRequest $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(CategoryRequest $request, $id)
    {
        $this->authorize('update', Category::class);
        if($request->company_id){
            $company_id =  $request->company_id;
        }else{
            $company_id = \Auth::user()->company_id;
        }
        Category::where('id',$id)->update([
            'category_name'=> trim($request->category_name),
//            'property_id' => $request->property_id,
            'company_id' => $company_id
        ]);
        return redirect('admin/category')->with('success','Successfully Updated');
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
        $this->authorize('destroy', Category::class);
        $category = Category::findOrFail($id);
        $category->delete();

        return redirect('admin/category')->with('success','Successfully Deleted');
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
        $this->authorize('view', Category::class);
        $columns = array(
            0 => 'category_name',
            1 => 'company_name',
            2 => 'property_name',
            3 => 'action',
        );

        if(\Auth::user()->hasRole('superAdmin')){
            $totalData = Category::count();
        }else{
            $totalData = Category::where('company_id',\Auth::user()->company_id)->orWhere('global','1')->count();
        }

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');


        $query = DB::table('categories as c')
                    ->leftJoin('companies as co', 'co.id', '=', 'c.company_id')
                    ->leftJoin('properties as p', 'p.id', '=', 'c.property_id')
                    ->select('c.id','c.category_name','c.global','c.company_id','c.property_id','co.name as company_name','p.property_name')
                    ->whereNull('c.deleted_at');
            if(!\Auth::user()->hasRole('superAdmin')) {
                $query = $query->where('c.company_id', \Auth::user()->company_id)
                    ->orWhere('c.global', '1');
            }
            $cat_name_search = $request->columns[0]['search']['value'];
            if(!empty($cat_name_search)){
                $query->where('c.category_name','like',$cat_name_search.'%');
            }
            $com_name_search = $request->columns[1]['search']['value'];
            if(!empty($com_name_search)){
                $query->where('co.company_name','like',$com_name_search.'%');
            }
            $prop_name_search = $request->columns[2]['search']['value'];
            if(!empty($prop_name_search)){
                $query->where('p.property_name','like',$prop_name_search.'%');
            }
            $results = $query->skip($start)->take($limit)->orderBy($order,$dir)->get();

        $totalFiltered = $totalData;

//        $id_arr = array();
//        $data = array();

        if($results){
            foreach($results as $r){
                $id_arr[] = $r->id;
                $nestedData['category_name'] = $r->category_name;
                $nestedData['company_name'] = (!empty($r->company_name))?$r->company_name:'<span class="float-right">[Standard]</span>';
                $nestedData['property_name'] = (!empty($r->property_name))?$r->property_name:'<span class="float-right">[Standard]</span>';
                $nestedData['action'] = \View::make('admin.category.action')->with('r',$r)->render();
                $data[] = $nestedData;
            }
        }

//        $HighestID = (!empty($id_arr))?max($id_arr):NULL;
//        $LowestID = (!empty($id_arr))?min($id_arr):NULL;
        $json_data = array(
            "draw"          => intval($request->input('draw')),
            "recordsTotal"  => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"          => $data,
//            "HighestID" => $HighestID,
//            "LowestID" => $LowestID
        );

        echo json_encode($json_data);
        exit();

    }

    public function getReplace($id){
        if(!\Auth::user()->hasRole('superAdmin')){
            abort('404');
        }
        $category = Category::findOrFail($id);
        $global_categories = Category::where('global',1)->get();
        return view('admin.category.replace',compact('category','global_categories'));

    }

    public function postReplace(Request $request,$id){
        if(!\Auth::user()->hasRole('superAdmin')){
            abort('404');
        }
        $request->validate([
            'replace_by' => 'required|integer'
        ]);
        $category = Category::findOrFail($id);
        Amenity::where('category_id',$id)
            ->update(['category_id' => $request->replace_by]);
        AmenityCategoryMapping::where('category_id',$id)
            ->update(['category_id' => $request->replace_by]);
        if(isset($request->replace_and_delete)){
            $category->forceDelete();
        }
        return redirect()->route('category.index')->with('success','Replaced Successfully');
    }
}
