<?php

namespace App\Http\Controllers\Admin;

use App\Models\Amenity;
use App\Models\Floor;
use App\Models\Unit;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BuildingController extends Controller
{
    private $unitAdminController;
    public function __construct()
    {
        $this->unitAdminController = new UnitController();
        \View::share('page_title', 'Floor');
    }
    /**
     * Get floors of a building
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function floor($id)
    {
        $floors = Floor::where('building_id',$id)->orderBy('floor','asc')->get(['id','floor']);

        $units = $unitAmenityValues = array();

        if(count($floors)>0){
            $floor_id = $floors[0]->id;
            $units = Unit::where('floor_id',$floor_id)->get(['id','unit_number']);

            if(count($units)>0){
                $unit_id = $units[0]->id;
                $unitAmenityValues =  $this->unitAdminController->_amenityValues($unit_id);
            }

        }

        return response()->json([
            'floors' => $floors,
            'units' => $units,
            'amenities' => $unitAmenityValues
        ],200);

    }
}
