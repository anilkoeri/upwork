<?php


namespace App\Helpers;


use App\Http\Services\AmenityService;

use App\Models\FloorPlan;
use App\Models\Property;
use App\Models\UnitTypeDetail;
use Carbon\Carbon;
use League\Csv\Reader;
use DB;

class InsertSampleFloorPlan
{


    /**
     * Try to insert sample data if error occurs during first insertion, it will stops the further execution
     *
     * @param $data
     * @param $error_arr
     * @param $error_row_numbers
     * @return array
     * @throws \League\Csv\Exception
     */
    function insertSampleFloorPlanData($data,$error_arr,$error_row_numbers){

        $header_row = $data['header_row'];
        $offset = $data['offset'];
        $limit = $data['limit'];

        $filename = $data['file_name'];
        $service = new AmenityService();
        $dbase = new Property();
        $map_data = $data['map_data'];
//        $full_csv_header = $data['full_csv_header'];

        $csv_file_path = storage_path('app/files/floor-plan/').$filename;
        if (!ini_get("auto_detect_line_endings")) {
            ini_set("auto_detect_line_endings", TRUE);
        }
        $csv = Reader::createFromPath($csv_file_path, 'r');

        $input_bom = $csv->getInputBOM();

        if ($input_bom === Reader::BOM_UTF16_LE || $input_bom === Reader::BOM_UTF16_BE) {
            $csv->addStreamFilter('convert.iconv.UTF-16/UTF-8');
        }

//        $csv->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');

//        $csv->setOutputBOM(Reader::BOM_UTF8);
//        $csv->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');

        $csv->setHeaderOffset($header_row);

        $sample_data = $csv->fetchOne($header_row);

        $sample_data = array_merge($sample_data, $data['additional_value']);

        $rec_arr = array();

        $sample_data = array_values($sample_data);

        $floorPlanArr = array();
        foreach ($map_data as $mk => $mv) {
            if (isset($mv)) {
                $floorPlanArr[$mv] = $sample_data[$mk];
            }
        }
        $floorPlanArr['created_at'] = Carbon::now();
        $floorPlanArr['updated_at'] = Carbon::now();
        if (!array_key_exists('pricing_offset', $floorPlanArr)){
            $floorPlanArr['pricing_offset'] = '';
        }

//                $properties_arr[] = $floorPlanArr;

        DB::beginTransaction();
        try{
            $unitTypeDetail = UnitTypeDetail::firstOrCreate(
                [
                    'unit_type' => $floorPlanArr['unit_type'],
                    'property_id' => $data['property_id']
                ]
            );
            $floorPlan = FloorPlan::updateOrCreate(
                [
                    'pms_property' => empty($floorPlanArr['pms_property'])?NULL:$floorPlanArr['pms_property'],
                    'pms_unit_type' => empty($floorPlanArr['pms_unit_type'])?NULL:$floorPlanArr['pms_unit_type'],
                    'description' => isset($floorPlanArr['description'])?$floorPlanArr['description']:NULL,
                    'beds' => isset($floorPlanArr['beds'])?$floorPlanArr['beds']:NULL,
                    'baths' => isset($floorPlanArr['baths'])?(int)$floorPlanArr['baths']:NULL,
                    'sqft' => empty($floorPlanArr['sqft'])?NULL:$floorPlanArr['sqft'],
                    'unit_count' => ($floorPlanArr['unit_count'] == '')?NULL:$floorPlanArr['unit_count'],
                    'pricing_offset' => (empty($floorPlanArr['pricing_offset']) &&  $floorPlanArr['pricing_offset'] != '0')?NULL:$floorPlanArr['pricing_offset'],
                    'unit_type_id' => $unitTypeDetail->id,
                    'property_id' => $data['property_id'],
                ]
            );
        } catch (\Exception $e) {
            $error_arr = $e->getMessage();
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

}
