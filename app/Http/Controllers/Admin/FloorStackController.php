<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\FloorStack;
use App\Models\Amenity;
use App\Models\AmenityValue;
use App\Models\Building;
use App\Models\Category;
use App\Models\Company;
use App\Models\Property;
use App\Models\Review;
use App\Models\Unit;
use App\Models\UnitAmenityValue;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\SearchCategory;

use Auth, DB;

class FloorStackController extends Controller
{
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
//        $properties = $all_properties->reject(function($property){
//            if($property->buildings->count() == 0){
//                return true;
//            }
//        });
        $properties = $all_properties;


        if($request->property){
            $selectedProperty = $request->property;
            $property = Property::withCount('units')->findOrFail($selectedProperty);
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
            $property = Property::withCount('units')->find($selectedProperty);
        }
        //load Categories
        $categories = Category::whereHas('amenities', function ($query) use ($selectedProperty) {
                $query->where('property_id', $selectedProperty);
            })->with(['amenities' => function($query) use($selectedProperty){
                $query->where('property_id',$selectedProperty)->orderByRaw('LENGTH(amenity_name)', 'ASC')->orderBy('amenity_name');
            },'amenities.amenityValues','amenities.amenityValues.unitAmenityValues'])
            ->orderBy('category_name')
//            ->where('property_id',$selectedProperty)
//            ->orWhere('global','1')
            ->get();

//        $categories = Category::with(['amenities' => function($query) {
//            $query->orderBy('amenity_name');
//        },'amenities.amenityValues','amenities.amenityValues.unitAmenityValues'])
//            ->orderBy('category_name')
//            ->where('company_id',$selectedCompany)
//            ->orWhere('global','1')
//            ->get();

//        pe($categories);
        //Units count
        $total_units_count = 0;
        if(!empty($property)){
            $total_units_count = $property->units_count;
        }

        //Load search Data
        $auth_user = \Auth::user();
        $searchCat = SearchCategory::where('property_id', $selectedProperty)->where('user_id', $auth_user->id)->first();
        if (!$searchCat)
            $searchCat = SearchCategory::initialize();

        //load buildings id
        $buildings = Building::where('property_id',$selectedProperty)->pluck('id');
        return view('admin.floor-stack.index',compact('companies','properties','selectedCompany','property','categories','buildings','total_units_count', 'searchCat'));
    }

    /**
     * Called on floor-stack load.
     * Load buildings based on property and load floor-stack table based on the buildings
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function buildings(Request $request, $id)
    {
        $rules = [
            'categories_list' => 'required_without_all:amenities_list|array',
            'amenities_list' => 'required_without_all:categories_list|array',
        ];

        $customMessages = [
            'categories_list.required_without_all' => 'At least One Category need to be selected',
            'amenities_list.required' => 'At least One Category need to be selected',
        ];

        $this->validate($request, $rules, $customMessages);

        $buildings = Building::where('property_id',$id)
//            ->orderByRaw('LENGTH(building_number)','ASC')
//            ->orderByRaw('CAST(building_number as UNSIGNED)')
//            ->orderBy('building_number','asc')
            ->get(['id','building_number'])
            ->sortBy('building_number')
            ->values()
            ->all();

        $cnt = count($buildings);
        if($cnt > 1)
        {
            $building_id = '-1';
        }elseif($cnt > 0){
            $building_id = $buildings[0]->id;
        }else{
            $building_id = 0;
        }
        if(!empty($request->categories_list)){
            $categories_list = $request->categories_list;
        }else{
            $categories_list = array();
        }

        if(!empty($request->affordable_list)){
            $affordable_list = $request->affordable_list;
        }else{
            $affordable_list = array();
        }
        $res = FloorStack::getFloorStackTable($building_id,$categories_list,$request->amenities_list,$id);
        return response()->json([
            'buildings' => $buildings,
            'amenity_body' => $res[0],
            'valid_categories' => $res[1]
        ],200);

    }

    /**
     * @param Request $request
     * @param Property $property
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function flipAxis(Request $request,Property $property)
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

//        $property = Property::findOrFail($request->property_id);
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

        $amenity_body = FloorStack::getFloorStackTable($request->building_id,$request->categories_list,$request->amenities_list,$property->id,$request->affordable_list);

        return response()->json([
            'amenity_body' => $amenity_body[0]
        ],200);
    }

    /**
     * @param Request $request
     * @param Property $property
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function flipFloor(Request $request,Property $property)
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

//        $property = Property::findOrFail($request->property_id);
        if($property->axis == 2){
            $property->axis = 0;
        }else{
            $property->axis = 2;
        }
        $property->save();

        if(empty($request->categories_list)){
            $request->categories_list = array();
        }
        if(empty($request->amenities_list)){
            $request->amenities_list = array();
        }

        $amenity_body = FloorStack::getFloorStackTable($request->building_id,$request->categories_list,$request->amenities_list,$property->id,$request->affordable_list);

        return response()->json([
            'amenity_body' => $amenity_body[0]
        ],200);
    }

    /**
     * Called details to load details / floor-stack table with different parameter
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function details(Request $request,$id)
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

        $auth_user = Auth::user();
        $searchCat = SearchCategory::where('property_id', $id)->where('user_id', $auth_user->id)->first();
        if ($searchCat) {
            $aprDataDb['user_id'] = $auth_user->id;
            $aprDataDb['property_id'] = $id;
            $aprDataDb['building_ids'] = json_encode($request->building_id);
            $aprDataDb['cat_ids'] = json_encode($request->categories_list);
            $aprDataDb['amenities_list'] = json_encode($request->amenities_list);
            $searchCat->update($aprDataDb);
        } else {
            $aprDataDb['user_id'] = $auth_user->id;
            $aprDataDb['property_id'] = $id;
            $aprDataDb['building_ids'] = json_encode($request->building_id);
            $aprDataDb['cat_ids'] = json_encode($request->categories_list);
            $aprDataDb['amenities_list'] = json_encode($request->amenities_list);
            SearchCategory::create($aprDataDb);
        }

        $amenity_body = FloorStack::getFloorStackTable($request->building_id,$request->categories_list,$request->amenities_list,$id,$request->affordable_list);

        return response()->json([
            'amenity_body' => $amenity_body[0]
        ],200);
    }

    /**
     * List unitAmenities on each editUnitBlock
     *
     * @param Request $request
     * @param $property_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function listUnitAmenities(Request $request,$property_id)
    {
        if (empty($request->categories_list)) {
            $request->categories_list = array();
        }
        if (empty($request->amenities_list)) {
            $request->amenities_list = array();
        }

        $amenities = DB::table('units_amenities_values as uav')
            ->join('amenity_values as av', 'av.id', '=', 'uav.amenity_value_id')
            ->join('amenities as a', 'a.id', '=', 'av.amenity_id')
            ->join('categories as c', 'c.id', '=', 'a.category_id')
            ->whereIn('uav.unit_id', $request->unit_lists)
            ->select('a.id', 'a.amenity_name', 'av.id as av_id', 'av.initial_amenity_value', 'av.amenity_value', 'av.status', 'c.id as c_id', 'c.category_name')
            ->whereNull('uav.deleted_at')
            ->whereNull('a.deleted_at')
            ->distinct()
            ->orderBy('c.category_name', 'asc')
            ->orderBy('a.amenity_name', 'asc')
            ->get();

        $unitAmenityList = \View::make('admin.floor-stack._unitAmenityLists')
            ->with('amenities', $amenities)
            ->with('property_id', $property_id)
            ->with('building_ids', $request->building_id)
            ->with('unit_lists', $request->unit_lists)
            ->with('categories_list', $request->categories_list)
            ->with('amenities_list', $request->amenities_list)
            ->render();

        if (count($request->unit_lists) == 1) {
            $unit_id = $request->unit_lists[0];
            $unit = Unit::find($unit_id);
            return response()->json([
                'unitAmenityList' => $unitAmenityList,
                'unit' => $unit,
            ], 200);
        }else{
            return response()->json([
                'unitAmenityList' => $unitAmenityList,
            ], 200);
        }
    }

    /**
     * Save Unit Note
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeUnitNote(Request $request,$id)
    {
        $unit = Unit::find($id);
        DB::beginTransaction();
        if(trim($request->unit_note) == ''){
            Review::where('unit_id',$unit->id)
            ->where('action',4)
            ->delete();
        }else{
            Review::create([
                'unit_id' => $unit->id,
                'property_id' => $request->property_id,
                'action' => 4,
                'unit_note' => trim($request->unit_note),
                'created_by' => \Auth::user()->id
            ]);
        }
        $unit->unit_note = trim($request->unit_note);
        $unit->save();
        DB::commit();
        return response()->json([
            'success' => 'Successfully added'
        ],200);
    }

    public function removeAmenitiesFromUnits(Request $request,$property_id)
    {
        $unit_ids = json_decode($request->unit_lists);
        $amenity_value = AmenityValue::findOrFail($request->av_id);

//        $reviews = Review::where(function($q) use($unit_ids){
//            foreach ($unit_ids as $k => $id) {
//                if($k == 0){
//                    $q->whereRaw(
//                        'JSON_CONTAINS(multiple_units, \'["' . $id . '"]\')'
//                    );
//                }else{
//                    $q->orWhereRaw(
//                        'JSON_CONTAINS(multiple_units, \'["' . $id . '"]\')'
//                    );
//                }
//            }
//        })->get();

        //Find the actual units where there is amenity and needs to delete
        $actual_unit_ids = array();
        $actual_unit_ids = UnitAmenityValue::where('amenity_value_id',$request->av_id)
            ->whereIn('unit_id',$unit_ids)
//            ->whereNotNull('deleted_at')
            ->pluck('unit_id')
            ->toArray();

        DB::transaction(function () use($request,$actual_unit_ids,$amenity_value,$property_id) {
            UnitAmenityValue::where('amenity_value_id',$request->av_id)
                ->whereIn('unit_id',$actual_unit_ids)
                ->delete();

            if(count($actual_unit_ids) == 1){
                $u_id = reset($actual_unit_ids);
            }else{
                $u_id = NULL;
            }

            Review::create([
                'amenity_value_id' => $request->av_id,
                'multiple_units' => json_encode($actual_unit_ids),
                'unit_id' => $u_id,
                'property_id' => $property_id,
                'old_amenity_value' => $amenity_value->amenity_value,
                'action' => 3,
                'created_by' => \Auth::user()->id,
            ]);
        });
        $building_id = json_decode($request->building_ids);
        $categories_list = json_decode($request->categories_list);
        $amenities_list = json_decode($request->amenities_list);
        $amenity_body = FloorStack::getFloorStackTable($building_id,$categories_list,$amenities_list,$property_id );
        return response()->json([
            'am_id' => $request->am_id,
            'amenity_body' => $amenity_body[0],
            'message' => 'Successfully deleted'
        ],200);
    }

    /**
     * Return count of the total unit amenity value with same amenity based on amenity_id
     * Called to show how many rows could be effected, if a change is made to row in SelectedEditUnitAmenityBlock
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function amenityCount(Request $request,$id)
    {
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
        $request->validate([
            'uav_value' => 'required|numeric',
        ]);
        DB::beginTransaction();
        $delete_log = false;
        $amenityValue = AmenityValue::findOrFail($request->amenity_value_id);
        if($amenityValue->initial_amenity_value == $request->uav_value){
            $amenityValue->status = 0;
            $delete_log = true;
        }else{
            $amenityValue->status = 2;
        }
        $amenityValue->amenity_value = $request->uav_value;
        $amenityValue->save();

        if($delete_log){
            Review::where('amenity_value_id',$amenityValue->id)
                ->where('action',2)
                ->delete();
        }else{
            Review::create([
                'amenity_value_id' => $amenityValue->id,
//            'unit_id' => $amenityValue->unit_id,
                'property_id' => $request->property_id,
                'new_amenity_value' => $request->uav_value,
                'old_amenity_value' => $request->old_uav_value,
                'action' => 2,
                'created_by' => \Auth::user()->id
            ]);
        }
        DB::commit();

        $categories_list = json_decode($request->categories_list);
        $amenities_list = json_decode($request->amenities_list);
        $amenity_body = FloorStack::getFloorStackTable($request->building_id,$categories_list,$amenities_list,$request->property_id);

        return response()->json([
            'amenity_body'  => $amenity_body[0],
            'amenity_value' => $request->uav_value,
            'av_status'     => $amenityValue->status,
            'a_id'          => $id,
            'build_id'      => $request->g_building_id,
            'message'       => 'Successfully Updated',
//            'delete_log'    => $delete_log
        ],200);
    }

    /**
     * Add Amenities to Units
     * Assigning already created amenities/standard amenities to units
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addAmenitiesToUnits(Request $request)
    {
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
        //list of units where new amenities need to be added
        $unit_lists = json_decode($request->unit_lists);
        $building_ids = json_decode($request->building_ids);

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
        //actual_unit_ids consists unit_ids where amenities are already soft-deleted or not available at all.
        $actual_unit_ids = array();
        $actual_unit_ids = array_values(array_diff($unit_lists,$already_existed_unit_ids));

        $datas = array();

        foreach($actual_unit_ids as $uk => $uv){
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
        if(count($actual_unit_ids) == 1){
            $u_id = reset($actual_unit_ids);
        }else{
            $u_id = NULL;
        }
        if(count($actual_unit_ids) >= 1) {
            Review::create([
                'amenity_value_id' => $request->amenityValueSelect,
                'multiple_units' => json_encode($actual_unit_ids),
                'unit_id' => $u_id,
                'property_id' => $request->property_id,
                'action' => 1,
                'created_by' => \Auth::user()->id
            ]);
        }else{
            return response()->json([
                'sts'  => '-1',
                'info' => 'Nothing New To Add'
            ],200);
        }

        $amenityValue = AmenityValue::with(['amenity','amenity.category'])
            ->where('id',$request->amenityValueSelect)
            ->first();



        $categories_list = json_decode($request->categories_list);
        $amenities_list = json_decode($request->amenities_list);

        if(isset($categories_list[0]) && $categories_list[0] == -1){
            $filter = 'simple';
        }else{
            //check if it is filtered or not
            if (in_array($amenityValue->amenity->id, $amenities_list)) {
                $filter = 'highlighted';
            }else{
                $filter = 'unhighlighted';
            }
        }

        $amenity_body = FloorStack::getFloorStackTable($building_ids,$categories_list,$amenities_list,$request->property_id );

        return response()->json([
            'sts'  => '1',
            'amenity_body' => $amenity_body[0],
            'amenity_value' => $amenityValue,
//            'building_id' => $building_ids,
            'filter' => $filter,
            'success' => 'Successfully added'
        ],200);
    }

    /**
     * Add totally new amenity
     *
     * @param Request $request
     * @param $property_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function addNewAmenity(Request $request, $property_id)
    {

        $request->validate(
            [
                'categorySelect' => 'required',
                'amenity_name' => 'required|string',
                'amenity_value' => 'required|numeric',
            ],
            [
                'categorySelect.required' => 'Category Name is required',
                'categorySelect.string' => 'Category Name must be a string',
                'amenity_name.required' => 'Amenity Name is required',
                'amenity_name.string' => 'Amenity Name must be a string',
                'amenity_value.required' => 'Amenity Value is required',
                'amenity_value.numeric' => 'Amenity Value must be numeric'
            ]
        );

        $company_id = \Auth::user()->company_id;

        DB::beginTransaction();
            $category = Category::firstOrCreate(
                [
                    'category_name' => trim($request->categorySelect),
                    'company_id' => $company_id,
                    'property_id' => $property_id,
                ]
            );
            $amenity = Amenity::where('amenity_name',$request->amenity_name)
                ->where('category_id',$category->id)
                ->where('property_id',$property_id)
                ->withTrashed()
                ->first();

            if($amenity){
                if( $amenity->trashed() ){
                    $amenity->restore();
                }else{
                    $err = [
                        'amenity_name' => [
                            'Amenity Name Already Exists',
                        ]
                    ];
                    DB::rollback();
                    return response()->json(['errors' => $err],422);
                }
            }else{
                $amenity = Amenity::create([
                    'amenity_name'  => trim($request->amenity_name),
                    'category_id'   => $category->id,
                    'property_id'   => $property_id,
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s')
                ]);
            }
            $amenity_value = AmenityValue::where('amenity_id',$amenity->id)
                            ->where('amenity_value',$request->amenity_value)
                            ->withTrashed()
                            ->first();
            if($amenity_value){
                if($amenity_value->trashed()){
                    $amenity_value->restore();
                }
            }else{
                $amenity_value = AmenityValue::create([
                    'initial_amenity_value' => $request->amenity_value,
                    'amenity_value'         => $request->amenity_value,
                    'status'                =>  '1',
                    'amenity_id'            => $amenity->id,
                    'created_at'            => date('Y-m-d H:i:s'),
                    'updated_at'            => date('Y-m-d H:i:s')
                ]);
            }

            Review::create([
                'amenity_value_id' => $amenity_value->id,
                'property_id' => $property_id,
                'new_amenity_value' => $request->amenity_value,
                'action' => 5,
                'created_by' => \Auth::user()->id
            ]);

        DB::commit();
        return response()->json([
            'sts' => '1',
            'property_id' => $property_id,
            'success' => 'Successfully added'
        ],200);

    }


}
