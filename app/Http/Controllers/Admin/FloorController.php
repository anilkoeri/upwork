<?php

namespace App\Http\Controllers\Admin;

use App\Models\Amenity;
use App\Models\Unit;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class FloorController extends Controller
{
    private $unitAdminController;
    public function __construct()
    {
        $this->unitAdminController = new UnitController();
        \View::share('page_title', 'Floor');
    }
    /**
     * Return unit according to floor id
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function unit($id)
    {

        $units = Unit::where('floor_id',$id)->get(['id','unit_number']);

        $unitAmenityValues = array();

        if(count($units)>0){
            $unit_id = $units[0]->id;
            $unitAmenityValues =  $this->unitAdminController->_amenityValues($unit_id);
        }

        return response()->json([
            'units' => $units,
            'amenities' => $unitAmenityValues
        ],200);

    }
}
