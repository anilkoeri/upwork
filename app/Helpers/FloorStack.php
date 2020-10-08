<?php

namespace App\Helpers;

use App\Models\Building;
use App\Models\NonUnit;

class FloorStack
{
    /**
     * @param $building_id
     * @param $categories_list
     * @param $amenities_list
     * @param $property_id
     * @param null $affordable_list
     * @return array
     */
    public static function getFloorStackTable($building_id,$categories_list,$amenities_list,$property_id,$affordable_list = NULL){
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
                ->where('amenities.property_id',$property_id)
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
            $res = $query->orderBy('unit_number', 'asc')->get()->sortBy('building_number');

            $grouped = $res->groupBy('building_id');
        }else{
            $grouped = array();
        }

        $amenity_body_str = [];
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
                    $count = self::_countDigits($dv->stack);
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
                        $count = self::_countDigits($sv);
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
            $amenity_body = \View::make('admin.floor-stack._table')
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
//                ->with('building_unit_count',$building->units()->count())
                ->render();
            $amenity_body_str[] = [
                'id' => $gk,
                'table_data' => $amenity_body
            ];
        }

        return array($amenity_body_str,$valid_categories);
    }

    public static function _countDigits( $str )
    {
        return preg_match_all( "/[0-9]/", $str );
    }
}
