<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\NonUnitRequest;
use App\Models\Building;
use App\Models\NonUnit;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class NonUnitController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index()
    {
        $this->authorize('view', Unit::class);
        $properties = Property::all();
        return view('admin.non-unit.index',compact('properties'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->authorize('create', Unit::class);
        $nonUnit = new NonUnit();
        $properties = Property::all();
//        if(isset($properties[0])){
//            $buildings = Building::where('property_id',$properties[0]->id)->get();
//        }else{
//            $buildings = new Building();
//        }
        return view('admin.non-unit.create',compact('nonUnit','properties'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param NonUnitRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(NonUnitRequest $request)
    {
        $this->authorize('create', Unit::class);

        NonUnit::create(
            [
                'non_unit_number' => $request->non_unit_number,
                'building_id' => $request->building_id,
            ]
        );

        return redirect('admin/non-unit')->with('success','Created Successfully');

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
        $this->authorize('update', Unit::class);
        $nonUnit = NonUnit::with(['building.property'])->findOrFail($id);
        $properties = Property::all();
        return view('admin.non-unit.edit',compact('nonUnit','properties'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @param  NonUnitRequest $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(NonUnitRequest $request, $id)
    {
        $this->authorize('update', Unit::class);

        NonUnit::where('id',$id)->update([
            'non_unit_number'=>$request->non_unit_number,
            'building_id'=>$request->building_id
        ]);
        return redirect('admin/non-unit')->with('success','Successfully Updated');
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
        $nonUnit = NonUnit::findOrFail($id);
        $nonUnit->delete();

        return redirect('admin/non-unit')->with('success','Successfully Deleted');
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
            0 => 'non_unit_number',
            1 => 'action',
        );

        $sql_count = 'SELECT  
                            COUNT(*) as "count"
                        FROM 
                            non_units
                        WHERE 
                            building_id = ?';
        if ($request->building_id) {
            $totalData = DB::select($sql_count, [$request->building_id]);
            $totalData = $totalData[0]->count;

            $building_condition = 'B.id = ' . $request->building_id;
        } else {
            $totalData = NonUnit::count();

            $building_condition = '1 = 1';
        }
        $pagesize = $request->input('length');
        $start = $request->input('start');
        $pagenum = floor($start/$pagesize)+1;
        $search = $request->input('search.value');

        $pageRequest = isset($request->page)?$request->page:'first';
        $OldHighestID = isset($request->HighestID)?$request->HighestID:NULL;
        $OldLowestID = isset($request->LowestID)?$request->LowestID:NULL;

        if(!empty($request->building_id)) {
            if ($pageRequest == 'first') {
                $sql = 'SELECT NU.id, NU.non_unit_number, B.building_number, P.property_name
                    FROM non_units as NU
                    JOIN buildings as B
                    ON B.id = NU.building_id
                    JOIN properties as P
                    ON P.id = B.property_id
                    Where NU.building_id = ?
                    ORDER BY id DESC LIMIT ?';
                $results = DB::select($sql, [$request->building_id, $pagesize]);
            } else if ($pageRequest == 'previous') {

                $results = DB::select('SELECT * FROM   
                                    (  
                                      SELECT NU.id, NU.non_unit_number, B.building_number, P.property_name
                                      FROM non_units as NU
                                      JOIN buildings as B
                                      ON B.id = NU.building_id
                                      JOIN properties as P
                                      ON P.id = B.property_id
                                      where NU.id > ' . $OldHighestID . '
                                      AND NU.building_id = ' . $request->building_id . '
                                      order by id asc  
                                     limit ' . $pagesize . '
                                     ) as myAlias   
                                ORDER BY id desc');
            } else if ($pageRequest == 'next') {

                $sql = 'SELECT NU.id, NU.non_unit_number, B.building_number, P.property_name
                    FROM non_units as NU
                    JOIN buildings as B
                    ON B.id = NU.building_id
                    JOIN properties as P
                    ON P.id = B.property_id
                    WHERE NU.id < ? 
                    AND NU.building_id = ?
                    ORDER BY id DESC LIMIT ?';
                $results = DB::select($sql, [$OldLowestID, $request->building_id, $pagesize]);

            } else {
                $results = DB::select('SELECT * FROM   
                                 (SELECT NU.id, NU.non_unit_number, B.building_number, P.property_name
                                  FROM non_units as NU
                                  JOIN buildings as B
                                  ON B.id = NU.building_id
                                 JOIN properties as P
                                  ON P.id = B.property_id
                                  Where NU.building_id = ' . $request->building_id . '
                                  order by id asc 
                                  limit ' . $pagesize . ') as myAlias   
                              ORDER BY id desc');
            }
        }else{
            if($pageRequest == 'first')
            {
                $sql = 'SELECT NU.id, NU.non_unit_number, B.building_number, P.property_name
                    FROM non_units as NU
                    JOIN buildings as B
                    ON B.id = NU.building_id
                    JOIN properties as P
                    ON P.id = B.property_id
                    ORDER BY id DESC LIMIT ?';
                $results = DB::select($sql,[$pagesize]);
            }
            else if($pageRequest == 'previous'){

                $results = DB::select('SELECT * FROM   
                                    (  
                                      SELECT NU.id, NU.non_unit_number, B.building_number, P.property_name
                                      FROM non_units as NU
                                      JOIN buildings as B
                                      ON B.id = NU.building_id
                                      JOIN properties as P
                                      ON P.id = B.property_id
                                      where NU.id > '. $OldHighestID.'
                                      order by id asc  
                                     limit '.$pagesize.'
                                     ) as myAlias   
                                ORDER BY id desc');
            }
            else if($pageRequest == 'next'){

                $sql = 'SELECT NU.id, NU.non_unit_number, B.building_number, P.property_name
                    FROM non_units as NU
                    JOIN buildings as B
                    ON B.id = NU.building_id
                    JOIN properties as P
                    ON P.id = B.property_id
                    WHERE NU.id < ? 
                    ORDER BY id DESC LIMIT ?';
                $results = DB::select($sql,[$OldLowestID, $pagesize]);

            }
            else{
                $results = DB::select('SELECT * FROM   
                                 (SELECT NU.id, NU.non_unit_number, B.building_number, P.property_name
                                  FROM non_units as NU
                                  JOIN buildings as B
                                  ON B.id = NU.building_id
                                 JOIN properties as P
                                  ON P.id = B.property_id
                                  order by id asc 
                                  limit '.$pagesize.') as myAlias   
                              ORDER BY id desc');
            }
        }

        $totalFiltered = $totalData;

        $id_arr = array();
        $data = array();

        if($results){

//            pe($results);

            foreach($results as $r){
                $id_arr[] = $r->id;
                $nestedData['non_unit_number'] = $r->non_unit_number;
                $nestedData['property_name'] = $r->property_name;
                $nestedData['building_number'] = $r->building_number;
                $nestedData['action'] = \View::make('admin.non-unit.action')->with('r',$r)->render();
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
}
