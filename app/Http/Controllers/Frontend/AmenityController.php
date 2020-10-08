<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Services\AmenityService;
use App\Models\Amenity;
use App\Models\AmenityValue;
use App\Models\Building;
use App\Models\Category;
use App\Models\Company;
use App\Models\NonUnit;
use App\Models\Property;
use App\Models\Review;
use App\Models\Unit;
use App\Models\UnitAmenityValue;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

use League\Csv\Reader;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmenityController extends Controller
{
    private $table,$service;
    public function __construct()
    {
        $this->service = new AmenityService();
        $this->table = 'units_amenities_values';
    }
    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        $auth_user = \Auth::user();

        //load Companies
        if($auth_user->hasRole('superAdmin')){
            $all_companies = Company::with(['properties'])->get(['id','name']);
        }else{
            $all_companies = Company::with(['properties'])->where('id',$auth_user->company_id)->get(['id','name']);
        }
        $companies = $all_companies->reject(function($company){
            if($company->properties->count() == 0){
                return true;
            }
        });
        if($request->company){
            $selectedCompany = $request->company;
            if(!$auth_user->hasRole('superAdmin')){
                if($auth_user->company_id != $selectedCompany )
                {
                    abort('403');
                }
            }
        }else{
            if($companies->first()){
                $selectedCompany = $companies->first()->id;
            }else{
                $selectedCompany = '0';
            }
        }
        //load properties
        $all_properties = Property::with('buildings')->where('company_id',$selectedCompany)->get();
        $properties = $all_properties->reject(function($property){
            if($property->buildings->count() == 0){
                return true;
            }
        });

        if($request->property){
            $selectedProperty = $request->property;
            $property = Property::findOrFail($selectedProperty);
            if(!$auth_user->hasRole('superAdmin')){
                if($auth_user->company_id != $property->company_id )
                {
                    abort('403');
                }
            }
        }else{
            if($properties->first()){
                $selectedProperty = $properties->first()->id;
            }else{
                $selectedProperty = '0';
            }
            $property = Property::find($selectedProperty);
        }
        //load Categories
        $categories = Category::with(['amenities' => function($query) {
            $query->orderBy('amenity_name');
        },'amenities.amenityValues','amenities.amenityValues.unitAmenityValues'])
            ->orderBy('category_name')
            ->where('company_id',$selectedCompany)
            ->orWhere('global','1')
            ->get();

        //Units count
        $total_units_count = 0;
        if(!empty($property)){
            $total_units_count = $property->units()->count();
        }

        //load buildings id
        $buildings = Building::where('property_id',$selectedProperty)->pluck('id');
        return view('frontend.amenity.index',compact('companies','properties','selectedCompany','selectedProperty','categories','buildings','total_units_count'));
    }

    public function details(Request $request)
    {
        $rules = [
            'building_id' => 'array',
            'categories_list' => 'required_without_all:amenities_list|array',
            'amenities_list' => 'required_without_all:categories_list|array',
        ];

        $customMessages = [
            'categories_list.required_without_all' => 'At least One Category need to be selected',
            'amenities_list.required_without_all' => 'At least One Category need to be selected',
        ];

        $this->validate($request, $rules, $customMessages);

        if(empty($request->categories_list)){
            $request->categories_list = array();
        }
        if(empty($request->amenities_list)){
            $request->amenities_list = array();
        }

        $amenity_body = $this->_floorStack($request->building_id,$request->categories_list,$request->amenities_list,$request->property_id,$request->affordable_list);

        return response()->json([
            'amenity_body' => $amenity_body[0]
        ],200);
    }


    public function _floorStack($building_id,$categories_list,$amenities_list,$property_id = NULL,$affordable_list = NULL){
        $max_floor = $max_stack = 0;
        $min_floor = $min_stack = 999;
        $valid_categories = array();

        if(!empty($building_id)) {
            $non_unit = NonUnit::where('building_id', $building_id)
                ->pluck('non_unit_number')
                ->toArray();
            $query = \DB::table('units_amenities_values')
                ->join('units', 'units.id', 'units_amenities_values.unit_id')
                ->join('floors', 'floors.id', 'units.floor_id')
                ->join('buildings', 'buildings.id', 'floors.building_id')
                ->join('properties', 'properties.id', 'buildings.property_id')
                ->leftJoin('amenity_values', 'amenity_values.id', 'units_amenities_values.amenity_value_id')
                ->leftJoin('amenities', 'amenities.id', 'amenity_values.amenity_id')
                ->select('units_amenities_values.id as uav_id', 'units_amenities_values.uav_status', 'units_amenities_values.deleted_at as uav_deleted_at', 'amenities.id as amenity_id', 'amenities.amenity_name', 'amenity_values.amenity_value', 'amenity_values.status as av_status', 'floors.floor', 'units.id as unit_id', 'units.unit_number', 'units.unit_rent', 'units.stack', 'amenities.category_id', 'buildings.id as building_id','buildings.building_number')
                ->whereNull('buildings.deleted_at');

            if(is_array($building_id)){
                $query->whereIn('buildings.id', $building_id);
            }else{
                if ($building_id == -1) {
                    $query->where('buildings.property_id', $property_id);
                } else {
                    $query->where('buildings.id', $building_id);
                }
            }
//            $query->orderByRaw("CAST(buildings.building_number as UNSIGNED)")
//                ->orderBy('building_number', 'asc');
//                ->orderBy('unit_number', 'asc');
            $res = $query->orderBy('unit_number', 'asc')->get()->sortBy('building_number');
            $grouped = $res->groupBy('building_id');
        }else{
            $grouped = array();
        }

        $amenity_body_str = '';
        foreach($grouped as $gk => $data){
            $floors = $stacks = $new_data = $all_data = array();

            foreach ($data as $dk => $dv) {
                if (!in_array($dv->floor, $floors)) {
                    $floors[] = $dv->floor;
                }
                if (!in_array($dv->stack, $stacks)) {
                    $stacks[] = $dv->stack;
                }
                if (is_numeric($dv->stack)) {
                    $stack = str_pad($dv->stack, 2, "0", STR_PAD_LEFT);
                } elseif (is_numeric(substr($dv->stack, 0, 1))) {
                    $count = $this->_countDigits($dv->stack);
                    if ($count == 1) {
                        $stack = '0' . $dv->stack;
                    } else {
                        $stack = $dv->stack;
                    }
                } else {
                    $stack = $dv->stack;
                }

                //list valid categories (categories which are absent here should be hidden from floor stack page)
                if (!in_array($dv->category_id, $valid_categories))
                {
                    $valid_categories[] = $dv->category_id;
                }

                $new_data[$dv->floor . $stack][] = $dv;
            }
            asort($floors);
            asort($stacks);
            $f_numbers = $f_num_letters = $f_strings = $s_numbers = $s_num_letters = $s_strings = array();
            foreach ($floors as $fk => $fv) {
                if (is_numeric($fv)) {
                    $f_numbers[] = $fv;
                } elseif (preg_match('~[0-9]+~', $fv)) {
                    $f_num_letters[] = $fv;
                } else {
                    $f_strings[] = $fv;
                }
            }
            $floor_arr = array_merge($f_numbers, $f_num_letters, $f_strings);
            foreach ($stacks as $sk => $sv) {
                if (is_numeric($sv)) {
                    $s_numbers[] = str_pad($sv, 2, "0", STR_PAD_LEFT);
                } elseif (preg_match('~[0-9]+~', $sv)) {
                    if (is_numeric(substr($sv, 0, 1))) {
                        $count = $this->_countDigits($sv);
                        if ($count == 1) {
                            $s_num_letters[] = '0' . $sv;
                        } else {
                            $s_num_letters[] = $sv;
                        }
                    } else {
                        $s_num_letters[] = $sv;
                    }
                } else {
                    $s_strings[] = $sv;
                }
            }
            $stack_arr = array_merge($s_numbers, $s_num_letters, $s_strings);

            $building = Building::with(['property'])->where('id',$gk)->first();
            $amenity_body = \View::make('frontend.amenity._table')
                ->with('data',$new_data)
                ->with('floor_arr',$floor_arr)
                ->with('stack_arr',$stack_arr)
                ->with('categories',$categories_list)
                ->with('amenities',$amenities_list)
                ->with('affordables',$affordable_list)
                ->with('non_unit',$non_unit)
                ->with('building_id',$gk)
                ->with('building_number',$building->building_number)
                ->with('axis',$building->property->axis)
                ->with('building_unit_count',$building->units()->count())
                ->render();
            $amenity_body_str = $amenity_body_str.$amenity_body;
        }
//        $amenity_body = \View::make('frontend.amenity._table')
//            ->with('data',$new_data)
//            ->with('floor_arr',$floor_arr)
//            ->with('stack_arr',$stack_arr)
//            ->with('categories',$categories_list)
//            ->with('amenities',$amenities_list)
//            ->with('non_unit',$non_unit)
//            ->render();
//        \Debugbar::stopMeasure('render');
        return array($amenity_body_str,$valid_categories);
    }


    function _countDigits( $str )
    {
        return preg_match_all( "/[0-9]/", $str );
    }

    public function getExport(Request $request){
        $property = Property::findOrFail($request->pID);
        $review = Review::where('property_id',$request->pID)
            ->where('status',1)
            ->first();
        $alert = "";
        if($review){
            $alert = "This property has updates that need to be reviewed. Any unreviewed updates will not be exported.";
        }
        return view('frontend.amenity.export',compact('property','alert'));
    }

    public function exportCSV(Request $request){
        $request->validate([
            'file_name' => 'required',
        ]);
        $property = Property::with(['mappingTemplate'])->findOrFail($request->property_id);
        $data = array();
        if(isset($property->mappingTemplate)){
            $data = array(
                'csv_header' => $property->mappingTemplate->csv_header,
                'map_data' => $property->mappingTemplate->map_data
            );

        }
        $oldHighestId = 0;
        $properties = DB::select("SELECT uav.id, uav.uav_status, a.effective_date, a.inactive_date,
                                  a.amenity_name, a.amenity_code, a.brochure_flag,
                                  av.amenity_value, av.status,
                                  al.amenity_level,
                                  c.category_name,
                                  u.unit_number, u.Unit_ID, u.unit_code, u.unit_type, u.unit_sqft, u.unit_rent, u.stack, u.unit_note,
                                  f.floor,
                                  fg.floor_plan_code, fg.floor_plan_group_name, fg.floor_plan_rentable_square, fg.floor_plan_brochure_name, fg.FloorPlanID,
                                  b.building_number,
                                  p.property_name, p.property_code
                                  FROM units_amenities_values as uav
                                  JOIN amenity_values as av ON uav.amenity_value_id = av.id
                                  JOIN amenities as a ON av.amenity_id = a.id
                                  JOIN categories as c ON a.category_id = c.id
                                  JOIN units as u ON uav.unit_id = u.id
                                  JOIN floors as f ON f.id = u.floor_id
                                  JOIN buildings as b ON b.id = f.building_id
                                  JOIN properties as p ON p.id = b.property_id
                                  LEFT OUTER JOIN floor_groups as fg ON f.floor_group_id = fg.id
                                  LEFT OUTER JOIN amenity_levels as al ON al.amenity_id = a.id
                                  WHERE p.id = ".$property->id."
                                  ORDER BY id ASC
                                  "
        );
//        pe($properties);

        $map_data = json_decode($data['map_data']);

        $additional_map_data = ['status','unit_note'];
        $map_data = array_merge($map_data,$additional_map_data);

        $csv = Writer::createFromPath("php://temp", "r+");
        $csv_header = json_decode($data['csv_header']);

        $additional_csv_header = ['Status','Note'];
        $csv_header = array_merge($csv_header,$additional_csv_header);

        foreach($map_data as $mk => $mv){
            if(!isset($mv)){
                unset($map_data[$mk]);
                unset($csv_header[$mk]);
            }
        }

        $csv->insertOne($csv_header);

        $new_properties = array();
        foreach($properties as $property){
            $temp_arr = array();
            foreach($map_data as $mk => $mv){
                if($mv == 'status'){
                    if($property->status == 0){
                        if($property->uav_status == 1){
                            $property->status = 'Added';
                        }else{
                            $property->status = 'Unchanged';
                        }
                    }elseif($property->status == 2){
                        $property->status = 'Updated';
                    }else{
                        $property->status = 'Added (New)';
                    }
                }
                $temp_arr[] = $property->$mv;
            }
            $new_properties[] = $temp_arr;
        }

        $csv->insertAll($new_properties);

        $flush_threshold = 1000; //the flush value should depend on your CSV size.
        $content_callback = function () use ($csv, $flush_threshold) {
            foreach ($csv->chunk(1024) as $offset => $chunk) {
                echo $chunk;
                if ($offset % $flush_threshold === 0) {
                    flush();
                }
            }
        };

        $response = new StreamedResponse();
        $response->headers->set('Content-Encoding', 'none');
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');

        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $request->file_name.'.csv'
        );

        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Content-Description', 'File Transfer');
        $response->setCallback($content_callback);
        $response->send();

        return $response;

    }

    /**
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function unitAmenities(Request $request,$id){
        if(empty($request->categories_list)){
            $request->categories_list = array();
        }
        if(empty($request->amenities_list)){
            $request->amenities_list = array();
        }
        //for this process we need to have uav_status
//        $amenities_ids = DB::table('units_amenities_values as uav')
//            ->join('amenity_values as av', 'av.id', '=', 'uav.amenity_value_id')
//            ->where('uav.unit_id',$id)
//            ->whereNull('deleted_at')
//            ->distinct()
//            ->pluck('av.amenity_id')
//            ->toArray();
//
//        $amenities = DB::table('amenities as a')
//            ->join('amenity_values as av', 'av.amenity_id', 'a.id')
//            ->join('categories as c', 'c.id', '=', 'a.category_id')
//            ->select('a.id', 'a.amenity_name', 'av.id as av_id', 'av.initial_amenity_value', 'av.amenity_value', 'av.status', 'c.id as c_id', 'c.category_name' )
//            ->whereIn('a.id',$amenities_ids)
//            ->orderBy('c.category_name','asc')
//            ->orderBy('a.amenity_name','asc')
//            ->get();
//
//
//        pe($amenities);
//
//        $unitAemnityList = \View::make('frontend.amenity._selectedUnitLists')
//            ->with('amenities',$amenities)
//            ->with('building_id',$request->building_id)
//            ->with('unit',$id)
//            ->with('categories_list',$request->categories_list)
//            ->with('amenities_list',$request->amenities_list)
//            ->render();
//
//        return response()->json([
//            'unitAmenityList' => $unitAemnityList,
//        ],200);



//        pe($request->building_id);
//        $unit = Unit::with(['unitAmenityValues','unitAmenityValues.amenityValue','unitAmenityValues.amenityValue.amenity','unitAmenityValues.amenityValue.amenity.category'])->where('id',$id)->first();
        $unit = Unit::findOrFail($id);
        $amenities = DB::table('units as u')
            ->join('units_amenities_values as uav', 'uav.unit_id', 'u.id')
            ->join('amenity_values as av', 'av.id', 'uav.amenity_value_id')
            ->join('amenities as a', 'a.id', 'av.amenity_id')
            ->join('categories as c', 'c.id', 'a.category_id')
            ->select('a.id', 'a.amenity_name', 'av.id as av_id', 'av.initial_amenity_value', 'av.amenity_value', 'av.status', 'c.id as c_id', 'c.category_name', 'uav.uav_status' )
            ->where('u.id',$id)
            ->orderBy('c.category_name','asc')
            ->orderBy('a.amenity_name','asc')
            ->whereNull('uav.deleted_at')
            ->get();

        $unitAmenityList = \View::make('frontend.amenity._unitAmenityLists')
            ->with('unit',$id)
            ->with('amenities',$amenities)
            ->with('categories_list',$request->categories_list)
            ->with('amenities_list',$request->amenities_list)
            ->with('building_id',$request->building_id)
            ->render();

//        pe($unitAemnityList);
        return response()->json([
            'unitAmenityList' => $unitAmenityList,
            'unit_note' => $unit->unit_note,
            'unit_number' => $unit->unit_number,
        ],200);
    }

    /**
     * destroy unit amenity in editUnitBlock on the right side of floorStackView
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function destroyUnitAmenity(Request $request,$id)
    {

        DB::transaction(function () use($request,$id) {
            $uav = UnitAmenityValue::where('amenity_value_id',$request->av_id)
                ->where('unit_id',$id)
                ->first();
            Review::create([
                'amenity_value_id' => $request->av_id,
                'unit_id' => $id,
                'property_id' => $request->property_id,
                'old_amenity_value' => $uav->amenityValue->amenity_value,
                'action' => 3,
                'created_by' => \Auth::user()->id,
            ]);
            $uav->delete();
        });

//        $unitAmenity = UnitAmenityValue::with(['amenityValue'])->where('id',$id)->first();
//
//        Review::create([
//            'amenity_value_id' => $unitAmenity->amenityValue->id,
//            'unit_id' => $unitAmenity->unit_id,
//            'property_id' => $request->property_id,
//            'old_amenity_value' => $unitAmenity->amenityValue->amenity_value,
//            'action' => 3,
//            'created_by' => \Auth::user()->id,
//
//        ]);
//
//        $unitAmenity->delete();

        $categories_list = json_decode($request->categories_list);
        $amenities_list = json_decode($request->amenities_list);
        $amenity_body =$this->_floorStack($request->building_id,$categories_list,$amenities_list,$request->property_id);
        return response()->json([
            'amenity_body' => $amenity_body[0],
            'message' => 'Successfully deleted'
        ],200);
    }

    /**
     * Return count of the total unit amenity value with same amenity and same value
     * Called to show how many rows could be effected, if a change is made to row in editUnitAmenityBlock
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
//    public function amenityCount(Request $request)
//    {
//        $uav = UnitAmenityValue::findOrFail($request->uav_id);
////        $amenity_count = Amenity::where('id',$uav->amenity_id)
////            ->where('amenity_value',$uav->amenity_value)
////            ->count();
//        $uav_count = UnitAmenityValue::where('amenity_value_id',$uav->amenity_value_id)
////                        ->where('amenity_value',$uav->amenity_value)
//                        ->count();
//        return response()->json([
//            'amenity_value_id' => $uav->amenity_value_id,
//            'uav_count' => $uav_count
//        ],200);
//    }

    /**
     * update amenity value in editUnitBlock on the right side of floorStackView
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateUnitAmenity(Request $request, $id)
    {
//        pe($request->all());
        $request->validate([
            'uav_value' => 'required|numeric',
        ]);

        $amenityValue = AmenityValue::where('id',$request->amenity_value_id)->first();
        if($amenityValue->initial_amenity_value == $request->uav_value){
            $amenityValue->status = 0;
        }else{
            $amenityValue->status = 2;
        }
        $amenityValue->amenity_value = $request->uav_value;
        $amenityValue->save();


        Review::create([
            'amenity_value_id' => $amenityValue->id,
//            'unit_id' => $unitAmenityValue->unit_id,
            'property_id' => $request->property_id,
            'new_amenity_value' => $request->uav_value,
            'old_amenity_value' => $request->old_uav_value,
            'action' => 2,
            'created_by' => \Auth::user()->id
        ]);

        $categories_list = json_decode($request->categories_list);
        $amenities_list = json_decode($request->amenities_list);
        $amenity_body =$this->_floorStack($request->g_building_id,$categories_list,$amenities_list,$request->property_id );

        return response()->json([
            'amenity_body' => $amenity_body[0],
            'amenity_value' => $request->uav_value,
            'status' => $amenityValue->status,
//            'uav_status' => $unitAmenityValue->uav_status,
            'a_id' => $id,
            'message' => 'Successfully Updated'
        ],200);
    }

    public function storeUnitAmenities(Request $request,$id){
//        pe($request->all());
        $request->validate(
            [
                'categorySelect' => 'required',
                'amenitySelect' => 'required',
                'amenityValueSelect' => 'required'
            ],
            [
                'categorySelect.required' => 'Category Name is required',
                'amenitySelect.required' => 'Amenity Name is required',
                'amenityValueSelect.required' => 'Amenity Value Name is required',
            ]
        );
        $unitAmenityValue = UnitAmenityValue::where('unit_id',$id)
            ->where('amenity_value_id',$request->amenityValueSelect)
            ->withTrashed()
            ->first();
        if($unitAmenityValue){
            if($unitAmenityValue->trashed()){
                $unitAmenityValue->restore();
            }else{
                return response()->json([
                    'errors' => [
                        'amenitySelect' => [
                            'You can\'t add duplicate amenity to the unit'
                        ]
                    ]
                ],422);
            }
        }else{
            $unitAmenityValue = UnitAmenityValue::Create(
                [
                    'unit_id' => $id,
                    'amenity_value_id' => $request->amenityValueSelect,
                    'uav_status' => 1
                ]
            );
        }

//        if($unitAmenityValue->amenityValue->status == 1){
//            $sts = 5;
//        }else{
//            $sts = 1;
//        }

        Review::create([
            'amenity_value_id' => $request->amenityValueSelect,
            'unit_id' => $id,
            'property_id' => $request->property_id,
            'action' => 1,
            'created_by' => \Auth::user()->id
        ]);

        $unit = Unit::findOrFail($id);
        $amenities = DB::table('units as u')
            ->join('units_amenities_values as uav', 'uav.unit_id', 'u.id')
            ->join('amenity_values as av', 'av.id', 'uav.amenity_value_id')
            ->join('amenities as a', 'a.id', 'av.amenity_id')
            ->join('categories as c', 'c.id', 'a.category_id')
            ->select('a.id', 'a.amenity_name', 'av.id as av_id', 'av.initial_amenity_value', 'av.amenity_value', 'av.status', 'c.id as c_id', 'c.category_name', 'uav.uav_status' )
            ->where('u.id',$id)
            ->orderBy('c.category_name','asc')
            ->orderBy('a.amenity_name','asc')
            ->whereNull('uav.deleted_at')
            ->get();

        $categories_list = json_decode($request->categories_list);
        $amenities_list = json_decode($request->amenities_list);

        $unitAmenityList = \View::make('frontend.amenity._unitAmenityLists')
            ->with('unit',$id)
            ->with('amenities',$amenities)
            ->with('categories_list',$categories_list)
            ->with('amenities_list',$amenities_list)
            ->with('building_id',$request->building_id)
            ->render();


        $amenity_body =$this->_floorStack($request->building_id,$categories_list,$amenities_list );


//        if($categories_list[0] == -1){
//            $filter = 'simple';
//        }else{
//            //check if it is filtered or not
//            if (in_array($amenityValue->amenity->id, $amenities_list)) {
//                $filter = 'highlighted';
//            }else{
//                $filter = 'unhighlighted';
//            }
//        }

        return response()->json([
            'amenity_body' => $amenity_body[0],
            'valid_categories' => $amenity_body[1],
            'unitAmenityList' => $unitAmenityList,
            'unit_note' => $unit->unit_note,
            'unit_number' => $unit->unit_number,
            'success' => 'Successfully added'
        ],200);

    }

    public function storeCategoryAmenities(Request $request){

//        pe($request->all());
        $request->validate(
            [
                'categorySelect' => 'required',
                'amenitySelect' => 'required',
                'amenity_value' => 'numeric',
            ],
            [
                'categorySelect.required' => 'Category Name is required',
                'categorySelect.string' => 'Category Name must be a string',
                'amenitySelect.required' => 'Amenity Name is required',
                'amenitySelect.string' => 'Amenity Name must be a string',
//                'amenityValue.required' => 'Amenity Value is required',
                'amenityValue.numeric' => 'Amenity Value must be numeric'
            ]
        );

        $property = Property::find($request->property_id);
        $category = Category::firstOrCreate(
            [
                'category_name' => trim($request->categorySelect),
                'company_id' => $property->company_id,
                'property_id' => $request->property_id,
            ]
        );

//        $amenity = Amenity::where('amenity_name',$request->amenitySelect)
//                            ->where('category_id',$category->id)
//                            ->first();
//        if($amenity){
//            return response()->json([
//                'message' => 'There is already a amenity with name '.$request->amenitySelect.' for this property and its value is '.$amenity->amenity_value
//            ],422);
//        }

        $amenity = Amenity::firstOrCreate(
            [
                'amenity_name' => $request->amenitySelect,
                'category_id' => $category->id
            ]
        );
        $amenity_value = AmenityValue::firstOrCreate(
            [
                'amenity_id' => $amenity->id,
            ],
            [
                'initial_amenity_value' => $request->amenity_value,
                'amenity_value' => $request->amenity_value,
                'status' => 1
            ]
        );

        Review::create([
            'amenity_value_id' => $amenity_value->id,
            'property_id' => $request->property_id,
            'new_amenity_value' => $request->amenity_value,
            'action' => 5,
            'created_by' => \Auth::user()->id
        ]);

        return response()->json([
            'sts' => '1',
            'property_id' => $request->property_id,
            'success' => 'Successfully added'
        ],200);

    }

    public function storeUnitNote(Request $request,$id){
//        $request->validate(
//            [
//                'unitAmenitiesInput.*.category' => 'required_with:unitAmenitiesInput.*.amenity_name,unitAmenitiesInput.*.amenity_value',
//                'unitAmenitiesInput.*.amenity_name' => 'required_with:unitAmenitiesInput.*.category,unitAmenitiesInput.*.amenity_value',
//                'unitAmenitiesInput.*.amenity_value' => 'required_with:unitAmenitiesInput.*.amenity_name,unitAmenitiesInput.*.amenity_name',
//            ],[
//            'unitAmenitiesInput.*.category.required_with' => 'Category, Amenity Name and Amenity value all are required',
//            'unitAmenitiesInput.*.amenity_name.required_with' => 'Category, Amenity Name and Amenity value all are required',
//            'unitAmenitiesInput.*.amenity_value.required_with' => 'Category, Amenity Name and Amenity value all are required'
//        ]);
//        pe($request->all());
//        $request->validate([
//            'unit_note' => 'required'
//        ]);


        $unit = Unit::findOrFail($id);

        Review::create([
            'unit_id' => $unit->id,
            'property_id' => $request->property_id,
            'action' => 4,
            'unit_note' => $request->unit_note,
            'created_by' => \Auth::user()->id
        ]);

        $unit->unit_note = $request->unit_note;
        $unit->save();

//        $unit = Unit::with(['unitAmenityValues','unitAmenityValues.amenity','unitAmenityValues.amenity.category'])->where('id',$id)->first();
//        $unitAemnityList = \View::make('frontend.amenity._unitAmenityLists')
//            ->with('unitAmenity',$unit->unitAmenityValues)
//            ->render();
//
//
//        $categories_list = json_decode($request->categories_list);
//        $amenities_list = json_decode($request->amenities_list);
//        $amenity_body =$this->_floorStack($request->building_id,$categories_list,$amenities_list );


        return response()->json([
            'unit_note' => $unit->unit_note,
            'unit_number' => $unit->unit_number,
            'success' => 'Successfully added'
        ],200);

    }

    public function getSelectedUnitDetails(Request $request,$id)
    {
        if(empty($request->categories_list)){
            $request->categories_list = array();
        }
        if(empty($request->amenities_list)){
            $request->amenities_list = array();
        }
//        $unit = Unit::with(['unitAmenityValues','unitAmenityValues.amenityValue','unitAmenityValues.amenityValue.amenity.category'])->whereIn('id',$request->unit_lists)->get();

//        $uav = DB::table('units_amenities_values as uav')
//            ->join('amenity_values as av', 'av.id', '=', 'uav.amenity_value_id')
//            ->join('amenities as a', 'a.id', '=', 'av.amenity_id')
//            ->join('categories as c', 'c.id', '=', 'a.category_id')
//            ->select('uav.id', 'av.initial_amenity_value', 'av.amenity_value', 'a.id as am_id', 'a.amenity_name', 'c.category_name' )
////            ->groupBy('a.id')
//            ->get();

        $amenities_ids = DB::table('units_amenities_values as uav')
            ->join('amenity_values as av', 'av.id', '=', 'uav.amenity_value_id')
            ->whereIn('uav.unit_id',$request->unit_lists)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('av.amenity_id')
            ->toArray();

        $amenities = DB::table('amenities as a')
            ->join('amenity_values as av', 'av.amenity_id', 'a.id')
            ->join('categories as c', 'c.id', '=', 'a.category_id')
            ->select('a.id', 'a.amenity_name', 'av.id as av_id', 'av.initial_amenity_value', 'av.amenity_value', 'av.status', 'c.id as c_id', 'c.category_name' )
            ->whereIn('a.id',$amenities_ids)
            ->orderBy('c.category_name','asc')
            ->orderBy('a.amenity_name','asc')
            ->get();

        $unitAemnityList = \View::make('frontend.amenity._selectedUnitLists')
            ->with('amenities',$amenities)
            ->with('building_id',$request->building_id)
            ->with('unit_lists',$request->unit_lists)
            ->with('categories_list',$request->categories_list)
            ->with('amenities_list',$request->amenities_list)
            ->render();

        return response()->json([
            'unitAmenityList' => $unitAemnityList,
        ],200);
    }

    /**
     * Return count of the total unit amenity value with same amenity based on amenity_id
     * Called to show how many rows could be effected, if a change is made to row in SelectedEditUnitAmenityBlock
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function count(Request $request,$id)
    {
//        pe($request->all());
//        $av = AmenityValue::where('amenity_id',$id)->first();
        $uav_count = UnitAmenityValue::where('amenity_value_id',$request->avID)
            ->count();
        return response()->json([
            'amenity_id' => $id,
            'amenity_value_id' => $request->avID,
            'uav_count' => $uav_count
        ],200);
    }

    /**
     * Update value of an amenity by id
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateAmenityValue(Request $request,$id)
    {
//        pe($request->building_id);
        $request->validate([
            'uav_value' => 'required|numeric',
        ]);
        $amenityValue = AmenityValue::findOrFail($request->amenity_value_id);
        if($amenityValue->initial_amenity_value == $request->uav_value){
            $amenityValue->status = 0;
        }else{
            $amenityValue->status = 2;
        }
        $amenityValue->amenity_value = $request->uav_value;
        $amenityValue->save();

        Review::create([
            'amenity_value_id' => $amenityValue->id,
//            'unit_id' => $amenityValue->unit_id,
            'property_id' => $request->property_id,
            'new_amenity_value' => $request->uav_value,
            'old_amenity_value' => $request->old_uav_value,
            'action' => 2,
            'created_by' => \Auth::user()->id
        ]);

        $categories_list = json_decode($request->categories_list);
        $amenities_list = json_decode($request->amenities_list);
        $amenity_body =$this->_floorStack($request->g_building_id,$categories_list,$amenities_list,$request->property_id);

        return response()->json([
            'amenity_body' => $amenity_body[0],
            'amenity_value' => $request->uav_value,
            'av_status' => $amenityValue->status,
            'a_id' => $id,
            'build_id' => $request->g_building_id,
            'message' => 'Successfully Updated',
        ],200);
    }

    public function removeAmenitiesFromMultipleUnits(Request $request,$id)
    {
        $unit_ids = json_decode($request->unit_lists);
        $amenity_value = AmenityValue::findOrFail($request->av_id);
        DB::transaction(function () use($request,$unit_ids,$amenity_value) {
            UnitAmenityValue::where('amenity_value_id',$request->av_id)
                ->whereIn('unit_id',$unit_ids)
                ->delete();

            Review::create([
                'amenity_value_id' => $request->av_id,
                'multiple_units' => json_encode($unit_ids),
                'property_id' => $request->property_id,
                'old_amenity_value' => $amenity_value->amenity_value,
                'action' => 3,
                'created_by' => \Auth::user()->id,

            ]);
        });

        $categories_list = json_decode($request->categories_list);
        $amenities_list = json_decode($request->amenities_list);
        $amenity_body =$this->_floorStack($request->building_id,$categories_list,$amenities_list );
        return response()->json([
            'am_id' => $id,
            'amenity_body' => $amenity_body[0],
            'message' => 'Successfully deleted'
        ],200);
    }

    public function addAmenitiesFromMultipleUnits(Request $request)
    {

        $request->validate(
            [
                'categorySelect' => 'required',
                'amenitySelect' => 'required',
                'amenityValueSelect' => 'required'
            ],
            [
                'categorySelect.required' => 'Category Name is required',
                'amenitySelect.required' => 'Amenity Name is required',
                'amenityValueSelect.required' => 'Amenity Value Name is required',
            ]
        );
        //list of units where new amenities need to be added
        $unit_lists = json_decode($request->unit_lists);

        //list of unit ids where the amenities already existed
        $already_existed_unit_ids = DB::table('units_amenities_values')
            ->where('amenity_value_id',$request->amenityValueSelect)
            ->whereIn('unit_id',$unit_lists)
            ->whereNull('deleted_at')
            ->pluck('unit_id')
            ->toArray();

        //Restoring those UAV which are soft deleted
        UnitAmenityValue::where('amenity_value_id',$request->amenityValueSelect)
            ->whereIn('unit_id',$unit_lists)
            ->whereNotNull('deleted_at')
            ->restore();

        //filtering unit ids that yet don't have unit ids
        // (including soft deleted i.e, soft deleted row will be considered as non-existed means system will consider that row don't exist)
        $non_existed_ids = array_values(array_diff($unit_lists,$already_existed_unit_ids));

        $datas = array();

        foreach($non_existed_ids as $uk => $uv){
            $datas []= [
                'amenity_value_id' => $request->amenityValueSelect,
                'unit_id' => $uv,
                'uav_status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }

        //ignore rows that were soft deleted and already restored
        //but still those ids will be saved in review to re-delete in case the changes are rejected
        UnitAmenityValue::insertOrIgnore($datas);

        Review::create([
            'amenity_value_id' => $request->amenityValueSelect,
            'multiple_units' => json_encode($non_existed_ids),
            'property_id' => $request->property_id,
            'action' => 1,
            'created_by' => \Auth::user()->id
        ]);

        $amenityValue = AmenityValue::with(['amenity','amenity.category'])
            ->where('id',$request->amenityValueSelect)
            ->first();



        $categories_list = json_decode($request->categories_list);
        $amenities_list = json_decode($request->amenities_list);

        if($categories_list[0] == -1){
            $filter = 'simple';
        }else{
            //check if it is filtered or not
            if (in_array($amenityValue->amenity->id, $amenities_list)) {
                $filter = 'highlighted';
            }else{
                $filter = 'unhighlighted';
            }
        }

//        $unit = Unit::with(['unitAmenityValues','unitAmenityValues.amenityValue','unitAmenityValues.amenityValue.amenity','unitAmenityValues.amenityValue.amenity.category'])->where('id',$id)->first();
//        $unitAemnityList = \View::make('frontend.amenity._unitAmenityLists')
//            ->with('unitAmenity',$unit->unitAmenityValues)
//            ->with('unit',$unit)
//            ->with('categories',$categories_list)
//            ->with('amenities',$amenities_list)
//            ->render();

        $amenity_body =$this->_floorStack($request->building_id,$categories_list,$amenities_list );

        return response()->json([
            'amenity_body' => $amenity_body[0],
            'amenity_value' => $amenityValue,
            'building_id' => $request->building_id,
//            'unitAmenityList' => $unitAemnityList,
//            'unit_note' => $unit->unit_note,
//            'unit_number' => $unit->unit_number,
            'filter' => $filter,
            'success' => 'Successfully added'
        ],200);
    }

    public function flipAxis(Request $request)
    {
        $rules = [
            'building_id' => 'required',
            'categories_list' => 'required_without_all:amenities_list|array',
            'amenities_list' => 'required_without_all:categories_list|array',
        ];

        $customMessages = [
            'categories_list.required_without_all' => 'At least One Category need to be selected',
            'amenities_list.required_without_all' => 'At least One Category need to be selected',
        ];

        $this->validate($request, $rules, $customMessages);

        $property = Property::findOrFail($request->property_id);
        if($property->axis == 1){
            $property->axis = 0;
        }else{
            $property->axis = 1;
        }
        $property->save();


        if(empty($request->categories_list)){
            $request->categories_list = array();
        }
        if(empty($request->amenities_list)){
            $request->amenities_list = array();
        }

        $amenity_body = $this->_floorStack($request->building_id,$request->categories_list,$request->amenities_list,$request->property_id,$request->affordable_list);

        return response()->json([
            'amenity_body' => $amenity_body[0]
        ],200);
    }

}
