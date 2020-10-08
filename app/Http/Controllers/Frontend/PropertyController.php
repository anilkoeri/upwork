<?php

namespace App\Http\Controllers\Frontend;

use App\Models\Building;
use App\Models\NonUnit;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PropertyController extends Controller
{
    private $amenityControllerFront;
    public function __construct()
    {
        $this->amenityControllerFront = new AmenityController();
    }

    /**
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function building(Request $request, $id)
    {
//        $rules = [
//            'categories_list' => 'required_without_all:amenities_list|array',
//            'amenities_list' => 'required_without_all:categories_list|array',
//        ];
//
//        $customMessages = [
//            'categories_list.required_without_all' => 'At least One Category need to be selected',
//            'amenities_list.required' => 'At least One Category need to be selected',
//        ];
//
//        $this->validate($request, $rules, $customMessages);

        $buildings = Building::where('property_id',$id)
//            ->orderByRaw('LENGTH(building_number)','ASC')
//            ->orderByRaw('CAST(building_number as UNSIGNED)')
//            ->orderBy('building_number','asc')
            ->get(['id','building_number'])
            ->sortBy('building_number')
            ->values()
            ->all();

//        $res = \DB::table('buildings')
//            ->where('property_id',$id)
//            ->whereNull('deleted_at')
//            ->orderBy('building_number')
//            ->orderByRaw('CAST(building_number as UNSIGNED) asc')
//            ->pluck('building_number');
//
//        pe($res);


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
        $amenity_body = $this->amenityControllerFront->_floorStack($building_id,$categories_list,$request->amenities_list,$id);

        return response()->json([
            'buildings' => $buildings,
            'amenity_body' => $amenity_body[0],
            'valid_categories' => $amenity_body[1]
        ],200);




    }
}
