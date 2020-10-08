<?php

namespace App\Http\Controllers\Admin;

use App\Http\Services\AmenityService;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitAmenityValue;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use DB;

class UnitController extends Controller
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
        $this->authorize('view', Unit::class);
        $properties = Property::all();
        return view('admin.unit.index',compact('properties'));
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
        $this->authorize('destroy', Unit::class);
        $unit = Unit::findOrFail($id);
        $unit->delete();

        return redirect('admin/unit')->with('success','Successfully Deleted');
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
        $this->authorize('view', Unit::class);
        $columns = array(
            0 => 'unit_number',
            1 => 'building_number',
            2 => 'property_name',
            3 => 'action',
        );


        $sql_count = 'SELECT  
                            COUNT(*) as "count"
                        FROM 
                            units AS U
                              JOIN floors as F 
                              ON F.id = U.floor_id
                              JOIN buildings as B
                              ON B.id = F.building_id
                        WHERE 
                            B.id = ?';
        if ($request->building_id) {
            $totalData = DB::select($sql_count, [$request->building_id]);
            $totalData = $totalData[0]->count;

            $building_condition = 'B.id = ' . $request->building_id;
        } else {
            $totalData = Unit::count();

            $building_condition = '1 = 1';
        }
        $pagesize = $request->input('length');
        $start = $request->input('start');
        $pagenum = floor($start/$pagesize)+1;
        $search = $request->input('search.value');

        $pageRequest = isset($request->page)?$request->page:'first';
        $OldHighestID = isset($request->HighestID)?$request->HighestID:NULL;
        $OldLowestID = isset($request->LowestID)?$request->LowestID:NULL;



        if($pageRequest == 'first')
        {
            $sql = 'SELECT U.id, U.unit_number, B.building_number, P.property_name 
                    FROM units as U
                    JOIN floors as F 
                    ON F.id = U.floor_id
                    JOIN buildings as B
                    ON B.id = F.building_id
                    JOIN properties as P
                    ON P.id = B.property_id
                    WHERE '.$building_condition.'
                    ORDER BY id DESC LIMIT ?';
            $results = DB::select($sql,[$pagesize]);
        }
        else if($pageRequest == 'previous'){

            $results = DB::select('SELECT * FROM   
                                    (  
                                      SELECT U.id, U.unit_number, B.building_number, P.property_name 
                                      FROM units as U
                                      JOIN floors as F 
                                      ON F.id = U.floor_id
                                      JOIN buildings as B
                                      ON B.id = F.building_id
                                      JOIN properties as P
                                      ON P.id = B.property_id
                                      WHERE '.$building_condition.'
                                      AND U.id > '. $OldHighestID.'
                                      order by id asc  
                                     limit '.$pagesize.'
                                     ) as myAlias   
                                ORDER BY id desc');
        }
        else if($pageRequest == 'next'){

            $sql = 'SELECT U.id, U.unit_number, B.building_number, P.property_name 
                    FROM units as U
                    JOIN floors as F 
                    ON F.id = U.floor_id
                    JOIN buildings as B
                    ON B.id = F.building_id
                    JOIN properties as P
                    ON P.id = B.property_id
                    WHERE '.$building_condition.'
                    AND U.id < ? ORDER BY id DESC LIMIT ?';
            $results = DB::select($sql,[$OldLowestID, $pagesize]);

        }
        else{
            $results = DB::select('SELECT * FROM   
                                 (  SELECT U.id, U.unit_number, B.building_number, P.property_name 
                                    FROM units as U
                                    JOIN floors as F 
                                    ON F.id = U.floor_id
                                    JOIN buildings as B
                                    ON B.id = F.building_id
                                    JOIN properties as P
                                    ON P.id = B.property_id
                                    WHERE '.$building_condition.'
                                  order by id asc 
                                  limit '.$pagesize.') as myAlias   
                              ORDER BY id desc');
        }

        $totalFiltered = $totalData;

        $id_arr = array();
        $data = array();

        if($results){

//            pe($results);

            foreach($results as $r){
                $id_arr[] = $r->id;
                $nestedData['unit_number'] = $r->unit_number;
                $nestedData['building_number'] = $r->building_number;
                $nestedData['property_name'] = $r->property_name;
                $nestedData['action'] = \View::make('admin.unit.action')->with('r',$r)->render();
                $data[] = $nestedData;
            }
        }
//        p($id_arr);
        $HighestID = (!empty($id_arr))?max($id_arr):NULL;
        $LowestID = (!empty($id_arr))?min($id_arr):NULL;
        $json_data = array(
            "draw"          => intval($request->input('draw')),
            "recordsTotal"  => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"          => $data,
            "HighestID" => $HighestID,
            "LowestID" => $LowestID
        );

        echo json_encode($json_data);
        exit();
//        return response()->json([
//            $json_data
//        ],200);

    }



    /**
     * Toggle status of unit either published or unpublished
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatus(Request $request,$id)
    {
        $unit = Unit::findOrFail($id);

        if($unit->status == 1)
        {
            $status = 2;
        }else{
            $status = 1;
        }

        $unit->status = $status;
        $unit->save();
        return response()->json([
            'units' => $unit,
        ],200);

    }

    /**
     * Return array of categories of a unit
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCategory($id)
    {
        $unit = Unit::findOrFail($id);
        $catgories = $unit->categories->pluck('category_name','id');

        return response()->json([
            'categories' => $catgories
        ],200);

    }


    /**
     * Return amenities according to unit id
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function amenity($id)
    {
        $units = Unit::where('id',$id)->first(['id','unit_number']);

        $unitAmenityValues = $this->_amenityValues($id);

        return response()->json([
            'units' => $units,
            'amenities' => $unitAmenityValues
        ],200);

    }

    public function _amenityValues($unit_id)
    {
        $unitAmenityValues = UnitAmenityValue::with(['amenityValue','amenityValue.amenity'])->where('unit_id', $unit_id)->get();
        foreach($unitAmenityValues as $ak => $av){
//                    $av->assigned_categories = isset($av->amenity->category->category_name) ? $av->amenity->category->category_name : 'N/A';
            $av->action = \View::make('admin.property.amenity_action')->with('r',$av)->render();
        }

        return $unitAmenityValues;
    }

}
