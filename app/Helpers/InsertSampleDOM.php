<?php


namespace App\Helpers;


use App\Http\Services\AmenityService;
use App\Models\DOM;
use Carbon\Carbon;
use League\Csv\Reader;
use DB;

class InsertSampleDOM
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

        $offset = $data['offset'];
        $limit = $data['limit'];

        $filename = $data['file_name'];
        $service = new AmenityService();
        $dbase = new DOM();

        $map_data = $data['map_data'];
//        $full_csv_header = $data['full_csv_header'];

        $csv_file_path = storage_path('app/files/dom/').$filename;
        if (!ini_get("auto_detect_line_endings")) {
            ini_set("auto_detect_line_endings", TRUE);
        }
        $csv = Reader::createFromPath($csv_file_path, 'r');
        $csv->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');

        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');

        $csv->setHeaderOffset(0);
//        $csv_header = $csv->getHeader();
        $sample_data = $csv->fetchOne();
        $sample_data = array_merge($sample_data, $data['additional_value']);

        $rec_arr = array();
//        $properties_arr = array();

//        $stmt = (new Statement())
//            ->offset($offset)
//            ->limit(5)
//        ;
//
//        $records = $stmt->process($csv);
////        p($records);
//        foreach ($records as $record)
//        {
//            $record = array_merge($record, $data['additional_value']);
//            $rec_arr[] = array_values($record);
//        }
//        pe($rec_arr);
//        $records_arr = $service->trimArray($rec_arr);
//        p($map_data);
//        pe($records_arr);
        $sample_data = array_values($sample_data);

        $dom_arr = array();
        foreach ($map_data as $mk => $mv) {
            if (isset($mv)) {
                $dom_arr[$mv] = $sample_data[$mk];
            }
        }
        $dom_arr['created_at'] = Carbon::now();
        $dom_arr['updated_at'] = Carbon::now();

//                $properties_arr[] = $dom_arr;
        DB::beginTransaction();
        try{

            $unitTypeDetail = UnitTypeDetail::firstOrCreate(
                [
                    'unit_type' => $dom_arr['unit_type'],
                    'property_id' => $data['property_id']
                ]
            );
            $floorPlan = FloorPlan::create(
                [
                    'pms_property' => $dom_arr['pms_property'],
                    'floor_plan' => isset($dom_arr['floor_plan'])?$dom_arr['floor_plan']:NULL,
                    'description' => isset($dom_arr['description'])?$dom_arr['description']:NULL,
                    'beds' => isset($dom_arr['beds'])?$dom_arr['beds']:NULL,
                    'baths' => isset($dom_arr['baths'])?(int)$dom_arr['baths']:NULL,
                    'sqft' => $dom_arr['sqft'],
                    'unit_count' => $dom_arr['unit_count'],
                    'unit_type_id' => $unitTypeDetail->id,
                    'property_id' => $data['property_id']
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
