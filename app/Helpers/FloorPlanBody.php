<?php


namespace App\Helpers;


use App\Models\MappingTemplate;

class FloorPlanBody
{
    public static function getFloorPlanBody($data)
    {
        $mapping_template = MappingTemplate::where('table_name','floor_plans')->where('property_id',$data['property']->id)->first();

        $floorPlanBody = \View::make('admin.floor_plan._floorPlanTable')
            ->with('data',$data['records'])
            ->with('unitTypeDetails',$data['unitTypeDetails'])
            ->with('property',$data['property'])
            ->with('map_data',json_decode($mapping_template->map_data))
            ->render();
        return $floorPlanBody;
    }

    public static function getRentBody($data)
    {
        $floorPlanBody = \View::make('admin.floor_plan._rentDiv')
            ->with('data',$data['records'])
            ->with('unitTypeDetails',$data['unitTypeDetails'])
            ->with('property',$data['property'])
            ->render();
        return $floorPlanBody;
    }
}
