<?php


namespace App\Helpers;

use MathPHP\Statistics\Significance;
use MathPHP\Statistics\Average;
use MathPHP\Statistics\Descriptive;
use DB;
use Exception;

class APRHelper
{
    public static function getAprTableDetails($data)
    {

        $results = DB::table('amenities as a')
            ->leftJoin('amenity_values as av','av.amenity_id','=','a.id')
            ->leftJoin('units_amenities_values as uav','uav.amenity_value_id','=','av.id')
            ->leftJoin('units as u','uav.unit_id','=','u.id')
            ->leftJoin('amenity_pricing_reviews as apr','apr.unit_id','=','u.id')
            ->select('a.id as amenity_id','a.amenity_name','a.category_id', 'av.amenity_value','u.id as unit_id','u.unit_number','apr.dom','apr.building_id')
            ->where('a.property_id', $data['property_id'])
//            ->where('a.category_id', $data['category_id'])
            ->whereIn('apr.building_id', $data['building_ids'])
            ->whereNull('apr.deleted_at')
            ->whereNull('u.deleted_at')
            ->whereNull('av.deleted_at')
            ->whereNull('a.deleted_at')
            ->orderBy('a.amenity_name','asc')
            ->get()
            ->groupBy(['category_id','unit_id']);
//            ->groupBy('unit_id');

        //results related to selected category only on the filter
        $am_results = $results[$data['category_id']];
//        pe($am_results);
        $category_units_count = 0;
        $units_arr = array();
        $final_data1 = $final_data2 = array();
        $dom_arr = [];
        foreach($am_results as $ak => $av){

            $amenities_in_same_unit_count = count($av);

            /** check if one unit has more than one amenities from same category */

            if( $amenities_in_same_unit_count > 1){
                $concat_am_id = $concat_am_name = '';
                $amenity_value = 0;
                $dom = 0;
                $m_unit_dom = []; //added for t-test
                $same_amenity_arr = $temp_arr = [];

                /**
                 * concat amenity id name, value and other necessary things of those that are in same units
                 */
                foreach($av as $uk => $uv) {
                    if($uk != 0){
                        $concat_am_id .= '_';
                        $concat_am_name .= ' & ';
                    }
                    $concat_am_id .= $uv->amenity_id;
                    $concat_am_name .= $uv->amenity_name;
                    $amenity_value += $uv->amenity_value;
                    $dom += $uv->dom;
                    $m_unit_dom[] = $uv->dom; //added for t-test
                }
                if(in_array($concat_am_id,$data['checked_ids'])) {
                    $category_units_count += 1;
                    $obs = ($uv->dom != '') ? 1 : 0;
                    $units_arr[] = $uv->unit_id;

                    if (!isset($final_data2[$concat_am_id]['observation'])) {
                        $final_data2[$concat_am_id]['observation'] = 0;
                    }
                    if (!isset($final_data2[$concat_am_id]['dom'])) {
                        $final_data2[$concat_am_id]['dom'] = 0;
                    }
                    if (!isset($final_data2[$concat_am_id]['amenity_value'])) {
                        $final_data2[$concat_am_id]['amenity_value'] = 0;
                    }

                    $final_data2[$concat_am_id]['amenity_id'] = $concat_am_id;
                    $final_data2[$concat_am_id]['amenity_name'] = $concat_am_name;
                    $final_data2[$concat_am_id]['amenity_value'] += $amenity_value;
                    $final_data2[$concat_am_id]['unit_id'][] = $uv->unit_id;
                    $final_data2[$concat_am_id]['observation'] += $obs;
                    $final_data2[$concat_am_id]['dom'] += $dom;

                    //added for t-test
                    foreach ($m_unit_dom as $s_dom) {
                        if (!is_null($s_dom))
                            $dom_arr[$concat_am_id][] = $s_dom;
                    }
                }

            }else{

                /** if units has single amenity from one category **/
                foreach($av as $uk => $uv) {

                    if(in_array((string)$uv->amenity_id,$data['checked_ids'],false)) {
                        $category_units_count += 1;
                        $obs = ($uv->dom != '') ? 1 : 0;
                        $units_arr[] = $uv->unit_id;

                        if (!isset($final_data1[$uv->amenity_id]['observation'])) {
                            $final_data1[$uv->amenity_id]['observation'] = 0;
                        }
                        if (!isset($final_data1[$uv->amenity_id]['dom'])) {
                            $final_data1[$uv->amenity_id]['dom'] = 0;
                        }
                        if (!isset($final_data1[$uv->amenity_id]['amenity_value'])) {
                            $final_data1[$uv->amenity_id]['amenity_value'] = 0;
                        }

                        $final_data1[$uv->amenity_id]['amenity_id'] = $uv->amenity_id;
                        $final_data1[$uv->amenity_id]['amenity_name'] = $uv->amenity_name;
                        $final_data1[$uv->amenity_id]['amenity_value'] += $uv->amenity_value;
                        $final_data1[$uv->amenity_id]['unit_id'][] = $uv->unit_id;
                        $final_data1[$uv->amenity_id]['observation'] += $obs;
                        $final_data1[$uv->amenity_id]['dom'] += $uv->dom;

                        if (!is_null($uv->dom)) {
                            $dom_arr[$uv->amenity_id][] = $uv->dom; //added for t-test
                        }
                    }
                }
//                exit();

            }
        }
        //combining single amenity data with combo amenities data
        $final_data = $final_data1 + $final_data2;

        /** for none calculation */
        if(in_array('0',$data['checked_ids'])) {
            $none_data = array();
            $none_data['amenity_id'] = '0';
            $none_data['amenity_name'] = 'None';
            $none_data['amenity_value'] = '0';
            $none_data['unit_id'] = [];
            $none_data['observation'] = $none_data['dom'] = 0;
            foreach ($results as $rk => $rv) {
                //as none are other units those don't contains amenity from the specific category
                if ($rk != $data['category_id']) {
                    foreach ($rv as $rvk => $rvv) {
                        foreach ($rvv as $k => $v) {
                            $arr = array();
                            if (!in_array($v->unit_id, $units_arr)) {
                                if (!in_array($v->unit_id, $none_data['unit_id'])) {
                                    $none_data['unit_id'][] = $v->unit_id;
                                    $obs = ($v->dom != '') ? 1 : 0;
                                    $none_data['observation'] += $obs;
                                    $none_data['dom'] += $v->dom;
                                    if (!is_null($v->dom)) {
//                                    $none_dom[] = $v->dom;
                                        $dom_arr[0][] = $v->dom;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if (!empty($none_data['unit_id'])) {
                $final_data = array('0' => $none_data) + $final_data;
            }
        }

        //added for t-test
        $p1 = $dom_arr[$data['base_id']];
        $mean1   = Average::mean($p1);
        $size1 = count($p1);
        $sd1 = Descriptive::standardDeviation($p1);
        foreach($final_data as $fk => $fv){
            try {
                $mean2   = Average::mean($dom_arr[$fk]);
                $size2 = count($dom_arr[$fk]);
                $sd2 = Descriptive::standardDeviation($dom_arr[$fk]);
                if($size1 > 30 || $size2 > 30){
                    $tTest = Significance::zTestTwoSample($mean1, $mean2, $size1, $size2, $sd1, $sd2);
                }else{
                    $tTest = Significance::tTest($p1, $dom_arr[$fk]);
                }
                if(is_nan($tTest['p2']))
                    $tTest = Significance::zTestTwoSample($mean1, $mean2, $size1, $size2, $sd1, $sd2);
                
                $final_data[$fk]['chance_diff'] = round(((1 - $tTest['p2']) * 100), 1).'%';

            } catch (Exception $e) {
                $final_data[$fk]['chance_diff'] = 'N/A';
            }
        }

        $response = [
            'final_data'                    => $final_data,
            'category_units_count'          => $category_units_count,
        ];

        return $response;
    }
}
