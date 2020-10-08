<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 7/22/2019
 * Time: 8:21 AM
 */

namespace App\Http\Services;

use App\Models\Amenity;
use App\Models\AmenityLevel;
use App\Models\AmenityValue;
use App\Models\Building;
use App\Models\Category;
use App\Models\EmailTemplate;
use App\Models\Floor;
use App\Models\FloorGroup;
use App\Models\Property;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\UnitAmenityValue;
use DB;

use Carbon\Carbon;

use League\Csv\Reader;
use League\Csv\Statement;

class AmenityService
{
    /**
     * Validate either all the field from required all array are present,
     * at least One value present from the array
     *
     * @param array $required_all
     * @param array $required_at_least_one
     * @param int $row
     * @return array
     */
    public function validateField($required_all,$required_at_least_one,$row)
    {
        $error = 0;
        $err_msg = '';
        foreach ($required_all as $k => $v) {
            if(!in_array($v,$row))
            {
                $err_msg .=  '<span style="color: #DA4474">'. $v.' is required </span> <br>';
                $error++;
            }
        }
        if(count($required_at_least_one) > 0) {
            if (count(array_intersect($required_at_least_one, $row)) === 0) {
                $err_msg .= '<span style="color: #DA4474"> At least one value among ( ' . implode(', ', $required_at_least_one) . ' ) is required </span><br>';
                $error++;
            }
        }
        $ret = array(
            'err_msg' => $err_msg,
            'error' => $error,
        );
        return $ret;
    }

    /**
     * Trim Array
     *
     * @param array $arr
     * @return array
     */
    public function trimArray($arr)
    {
        $final = array();

        foreach($arr as $k => $v)
        {
            if(array_filter($v)) {
                $final[] = $v;
            }
        }

        return $final;

    }

    /**
     * GET MESSAGE BY SLUG
     *
     * @param string $slug
     * @return string
     */
    public function getMessageBySlug($slug)
    {
        $email_template =  EmailTemplate::where('slug',$slug)->firstOrFail();
        return $email_template;
    }

    /**
     * GET SETTING BY SLUG
     *
     * @param string $slug
     * @return string
     */
    public function getSettingBySlug($slug)
    {
        $setting =  Setting::where('slug',$slug)->firstOrFail();
        return $setting->value;
    }

    /**
     * Return the type of a column such as string, date, number
     *
     * @param string $table name of table
     * @param string $name name of column
     * @return mixed
     */
    public function getDatabaseColumnType($table,$name)
    {
        $row = DB::select("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '".$table."' AND COLUMN_NAME = '".$name."'");
        $data = array_shift($row);
        return $data->DATA_TYPE;

    }

    /**
     * Check the date is in correct format
     * @param $date
     * @param string $format
     * @return bool
     */
    function validateDate($date, $format = 'Y-m-d')
    {
        if(!empty($date) && strtolower($date) != 'null') {
            $d = \DateTime::createFromFormat($format, $date);
            // The Y ( 4 digits year ) returns TRUE for any integer with any number of digits so changing the comparison from == to === fixes the issue.
            if ($d && $d->format($format) === $date) {
                return true;
            } else {
                return false;
            };
        }else{
            return true;
        }
    }

    /**
     * Try to insert sample data if error occurs during first insertion, it will stops the further execution
     *
     * @param $data
     * @param $error_arr
     * @param $error_row_numbers
     * @return array
     * @throws \League\Csv\Exception
     */
    function insertSampleData($data,$error_arr,$error_row_numbers){

        $header_row = $data['header_row'];
        $offset = $data['offset'];
        $limit = $data['limit'];

        $filename = $data['file_name'];
        $service = new AmenityService();
        $dbase = new Property();

        $map_data = $data['map_data'];
//        $full_csv_header = $data['full_csv_header'];

        $csv_file_path = storage_path('app/files/property/').$filename;
        if (!ini_get("auto_detect_line_endings")) {
            ini_set("auto_detect_line_endings", TRUE);
        }
        $csv = Reader::createFromPath($csv_file_path, 'r');
        $csv->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');
        $csv->skipEmptyRecords();
        $csv->setHeaderOffset($header_row);
//        $csv_header = $csv->getHeader();
        $sample_data = $csv->fetchOne($header_row);

        $sample_data = array_merge($sample_data, $data['additional_value']);

        $rec_arr = array();

        $sample_data = array_values($sample_data);

                $property_arr = array();
                foreach ($map_data as $mk => $mv) {
                    if (isset($mv)) {
                        $property_arr[$mv] = $sample_data[$mk];
                    }
                }
                $property_arr['created_at'] = Carbon::now();
                $property_arr['updated_at'] = Carbon::now();

//                $properties_arr[] = $property_arr;

                DB::beginTransaction();
                try{
                    if($data['fs_col'] < 2){
                        $unit_number = !empty($property_arr['unit_number'])?trim($property_arr['unit_number']):'';
                        if($unit_number == ''){
                            throw new \ErrorException('Unit Number is missing in row '.($header_row+2));
                        }
                        $splitted_unit_number = str_split($unit_number);
                        $str_len = strlen($unit_number);
                        $fsb_row = $data['fsb'][$str_len];

                        $building = $floor = $stack = '';
                        foreach($fsb_row as $fk => $fv){
                            if($fv == 'building'){
                                $building .= $splitted_unit_number[$fk];
                            }
                            if($fv == 'floor'){
                                $floor .= $splitted_unit_number[$fk];
                            }
                            if($fv == 'stack'){
                                $stack .= $splitted_unit_number[$fk];
                            }
                        }
                        if($data['fs_col'] == 1){
                            $building = isset($property_arr['building_number'])?trim($property_arr['building_number']):'1';
                        }
                    }else{

                        $building = isset($property_arr['building_number'])?trim($property_arr['building_number']):'1';
                        $unit_number = !empty($property_arr['unit_number'])?trim($property_arr['unit_number']):'';
                        $floor = isset($property_arr['floor'])?trim($property_arr['floor']):NULL;
                        $stack = isset($property_arr['stack'])?trim($property_arr['stack']):NULL;
                    }

                    $property = Property::updateOrCreate(
                        ['id' => $data['property_id']],
                        [
                            'property_code' => isset($property_arr['property_code'])?$property_arr['property_code']:NULL,
                        ]
                    );
                    $building = Building::firstOrCreate(
                        [
                            'building_number' => !empty($building)?$building:'1',
                            'property_id' => $property->id,
                        ]
                    );
                    if(isset($property_arr['floor_plan_code'])) {
                        $floor_group = FloorGroup::firstOrCreate(
                            [
                                'floor_plan_code' => $property_arr['floor_plan_code']
                            ],
                            [
                                'floor_plan_group_name' => isset($property_arr['floor_plan_group_name']) ? $property_arr['floor_plan_group_name'] : NULL,
                                'floor_plan_rentable_square' => isset($property_arr['floor_plan_rentable_square']) ? $property_arr['floor_plan_rentable_square'] : NULL,
                                'floor_plan_brochure_name' => isset($property_arr['floor_plan_brochure_name']) ? $property_arr['floor_plan_brochure_name'] : NULL,
                                'FloorPlanID' => isset($property_arr['FloorPlanID']) ? $property_arr['FloorPlanID'] : NULL,
                            ]
                        );
                    }
                    $floor = Floor::firstOrCreate(
                        [
                            'floor' => isset($floor)?$floor:NULL,
                            'building_id' => $building->id,
                            'floor_group_id' => isset($floor_group->id) ? $floor_group->id : NULL
                        ]
                    );

                    $unit = Unit::firstOrCreate(
                        [
                            'unit_number' => $unit_number,
                            'floor_id' => $floor->id,
                            'building_id' => $building->id,
                        ],
                        [
                            'Unit_ID' => isset($property_arr['Unit_ID'])?$property_arr['Unit_ID']:NULL,
                            'unit_code' => isset($property_arr['unit_code'])?$property_arr['unit_code']:NULL,
                            'unit_type' => isset($property_arr['unit_type'])?$property_arr['unit_type']:NULL,
                            'unit_sqft' => isset($property_arr['unit_sqft'])?$property_arr['unit_sqft']:NULL,
                            'unit_rent' => isset($property_arr['unit_rent'])?preg_replace("/[^0-9.]/", "", $property_arr['unit_rent']):NULL,
                            'stack' => isset($stack)?$stack:NULL,
                        ]
                    );

//                    $category = Category::firstOrCreate(
//                        [
//                            'category_name' => $property_arr['category_name'],
//                            'property_id' => $property->id
//                        ]
//                    );
                    $amenity = Amenity::firstOrCreate(
                        [
                            'amenity_name' => ($property_arr['amenity_name'] != '')?trim($property_arr['amenity_name']):'',
                            'category_id' => $data['map_cat_arr'][$property_arr['amenity_name']],
                            'property_id' => $property->id
                        ],
                        [
                        'amenity_code' => isset($property_arr['amenity_code'])?$property_arr['amenity_code']:NULL,
                        'brochure_flag' => isset($property_arr['brochure_flag'])?$property_arr['brochure_flag']:NULL,
                        'effective_date' => isset($property_arr['effective_date'])?$property_arr['effective_date']:NULL,
                        'inactive_date' => isset($property_arr['inactive_date'])?$property_arr['inactive_date']:NULL,
                        ]
                    );
                    $am_value = $property_arr['amenity_value'];
                    if( preg_match( '!\(([^\)]+)\)!', $am_value, $match ) ){
                        if( strpos($match[1], '-') !== false ) {
                            $am_value = $match[1];
                        }else{
                            $am_value = '-'.$match[1];
                        }
                    }

                    $amenity_value = AmenityValue::firstOrCreate(
                        [
                            'amenity_id' => $amenity->id,
                            'initial_amenity_value' => ($am_value != '')?preg_replace("/[^0-9.-]/", "", $am_value):NULL
//                            'property_id' => $property->id
                        ],
                        [
//                            'initial_amenity_value' => preg_replace("/[^0-9.-]/", "", $am_value),
                            'amenity_value' => ($am_value != '')?preg_replace("/[^0-9.-]/", "", $am_value):NULL,
                        ]
                    );

                    if(isset($property_arr['amenity_level'])) {
                        $amenityLevel = AmenityLevel::firstOrCreate(
                            [
                                'amenity_level' => $property_arr['amenity_level'],
                                'amenity_id' => $amenity->id
                            ]
                        );
                    }

                    $unitAmenityValue = UnitAmenityValue::updateOrCreate(
                        [
                            'unit_id' => $unit->id,
                            'amenity_value_id' => $amenity_value->id
                        ]
                    );
                } catch (\Exception $e) {
                    if(isset($e->errorInfo)){
                        $error_arr = $e->errorInfo[2];
                        $error_arr = str_replace('at row 1', '', $error_arr);
                    }else{
                        $error_arr = $e->getMessage();
                    }

//                    pe($error_arr);
//                    $err_msg = $e->getPrevious()->getMessage();
//                    $columns = preg_match("/'.*?'/", $err_msg, $matches);
//                    pe($matches->first());
//                    $b = preg_match('/(Data truncated for column) \'([a-zA-Z_]+)\'/', $err, $matches);
//                    if ( $b ) {
//                        // Found data truncated
//                        $message = $matches[1];
//                        $columnName = $matches[2];
//                    }
//                    pe($matches);
////                    $e->getPrevious()->getErrorCode()
////                    pe($e->getPrevious()->getMessage());
//                    pe($e->errorInfo);

                }
                DB::rollBack();

            $arr_data = [
                'error' => $error_arr,
            ];

        if (!ini_get("auto_detect_line_endings")) {
            ini_set("auto_detect_line_endings", FALSE);
        }

        return $arr_data;
    }

    public function formatMailBody($slug,$array_from_to)
    {
        $data = $this->getMessageBySlug($slug);
        $body = str_replace(array_keys($array_from_to), $array_from_to, $data->body);
        return $body;
    }

    function checkIfContainsWord( $needle, $haystack ) {
        return preg_match( '#\b' . preg_quote( $needle, '#' ) . '\b#i', $haystack ) !== 0;
    }

    /**
     * Checks if key value exists in multidimensional array
     *
     * @param $array
     * @param $key
     * @param $val
     * @return bool
     */
    public function findKeyValueMultiDimensional($array, $key, $val)
    {
        foreach ($array as $item)
        {
            if (is_array($item) && self::findKeyValueMultiDimensional($item, $key, $val)) return true;

            if (isset($item[$key]) && $item[$key] == $val) return true;
        }

        return false;
    }



//    function whatever($array, $key, $val) {
//        foreach ($array as $item)
//            if (isset($item[$key]) && $item[$key] == $val)
//                return true;
//        return false;
//    }

}
