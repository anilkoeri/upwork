<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\UnitAmenityValueRequest;
use App\Http\Services\AmenityService;
use App\Models\UnitAmenityValue;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UnitAmenityValueController extends Controller
{
    private $table,$service;
    public function __construct()
    {
        $this->service = new AmenityService();
        $this->table = 'units_amenities_values';
        \View::share('page_title', 'Unit Amenity Value');
    }

    public function getUnitAmenityValue($id)
    {
        $unitAmenityValue = UnitAmenityValue::find($id);
        return response()->json([
            'unitAmenityValue' => $unitAmenityValue
        ],200);
    }

    /**
     * @param UnitAmenityValueRequest $request
     * @param $id
     * @return mixed
     */
    public function postUnitAmenityValue(UnitAmenityValueRequest $request,$id)
    {
        $unitAmenityValue = UnitAmenityValue::findOrFail($id);
        $unitAmenityValue->amenity_value = $request->amenity_value;
//        $unitAmenityValue->effective_date = $request->effective_date;
//        $unitAmenityValue->inactive_date = $request->inactive_date;
        $unitAmenityValue->save();

        return response()->json([
            'unitAmenityValue' => $unitAmenityValue
        ],200);
    }
}
