<?php


namespace App\Helpers;


use App\Http\Services\AmenityService;
use App\Models\AmenityPricingReview;
use App\Models\Property;
use App\Models\Unit;
use Carbon\Carbon;
use League\Csv\Reader;

use DB;

class InsertSampleAPR
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
    function insertSampleAmenityPricingReviewData($data,$error_arr,$error_row_numbers){

        $header_row = $data['header_row'];
        $offset = $data['offset'];
        $limit = $data['limit'];

        $filename = $data['file_name'];
        $service = new AmenityService();
        $dbase = new Property();
        $map_data = $data['map_data'];
//        $full_csv_header = $data['full_csv_header'];

        $csv_file_path = storage_path('app/files/amenity-pricing-review/').$filename;
        if (!ini_get("auto_detect_line_endings")) {
            ini_set("auto_detect_line_endings", TRUE);
        }
        $csv = Reader::createFromPath($csv_file_path, 'r');

        $input_bom = $csv->getInputBOM();

        if ($input_bom === Reader::BOM_UTF16_LE || $input_bom === Reader::BOM_UTF16_BE) {
            $csv->addStreamFilter('convert.iconv.UTF-16/UTF-8');
        }

        $csv->setHeaderOffset($header_row);
        $sample_data = $csv->fetchOne($header_row);
        $sample_data = array_merge($sample_data, $data['additional_value']);
        $rec_arr = array();

        $sample_data = array_values($sample_data);

        $pricingReviewArr = array();
        foreach ($map_data as $mk => $mv) {
            if (isset($mv)) {
                $pricingReviewArr[$mv] = $sample_data[$mk];
            }
        }
        $pricingReviewArr['created_at'] = Carbon::now();
        $pricingReviewArr['updated_at'] = Carbon::now();

        $building_number = empty($pricingReviewArr['building_number'])?NULL:trim($pricingReviewArr['building_number']);
        $building_id = array_search($building_number, $data['building_details']);

        $unit_number = empty($pricingReviewArr['unit_number'])?NULL:trim($pricingReviewArr['unit_number']);
        $unit_id = Unit::where('unit_number',$unit_number)->where('building_id',$building_id)->value('id');

        if(empty($unit_id)){
//            $error_arr = "Unit Number ".$pricingReviewArr['unit_number']." was not found for building ".$pricingReviewArr['building_number'];
        }else{
            DB::beginTransaction();
            try{
                $apr = AmenityPricingReview::firstOrCreate(
                    [
                        'resident_id'               => empty($pricingReviewArr['resident_id'])?NULL:trim($pricingReviewArr['resident_id']),
                        'unit_id'                   => empty($unit_id)?NULL:$unit_id,
                        'building_id'               => $building_id,
                        'property_id'               => $data['property_id']
                    ],
                    [
                        'community_name'            => isset($pricingReviewArr['community_name'])?SanitizeData::formatNull($pricingReviewArr['community_name']):NULL,
                        'application_date'          => isset($pricingReviewArr['application_date'])?SanitizeData::formatDate($pricingReviewArr['application_date']):NULL,
                        'dom'                       => isset($pricingReviewArr['dom'])?SanitizeData::formatNull($pricingReviewArr['dom']):NULL,
                        'days_vacant'               => isset($pricingReviewArr['days_vacant'])?SanitizeData::formatNull($pricingReviewArr['days_vacant']):NULL,
                        'move_in_date'              => isset($pricingReviewArr['move_in_date'])?SanitizeData::formatDate($pricingReviewArr['move_in_date']):NULL,
                        'lease_end_date'            => isset($pricingReviewArr['lease_end_date'])?SanitizeData::formatDate($pricingReviewArr['lease_end_date']):NULL,
                        'notice_date'               => isset($pricingReviewArr['notice_date'])?SanitizeData::formatDate($pricingReviewArr['notice_date']):NULL,
                        'move_out_date'             => isset($pricingReviewArr['move_out_date'])?SanitizeData::formatDate($pricingReviewArr['move_out_date']):NULL,
                        'previous_notice_date'      => isset($pricingReviewArr['previous_notice_date'])?SanitizeData::formatDate($pricingReviewArr['previous_notice_date']):NULL,
                        'previous_move_out_date'    => isset($pricingReviewArr['previous_move_out_date'])?SanitizeData::formatDate($pricingReviewArr['previous_move_out_date']):NULL
                    ]
                );
            } catch (\Exception $e) {
//            pe($e);

                if(isset($e->errorInfo)){
                    $error_arr = $e->errorInfo[2];
                    $error_arr = str_replace('at row 1', '', $error_arr);
                }else{
                    $error_arr = $e->getMessage();
                }
            }
            DB::rollBack();
        }

        $arr_data = [
            'error' => $error_arr,
        ];

        if (!ini_get("auto_detect_line_endings")) {
            ini_set("auto_detect_line_endings", FALSE);
        }

        return $arr_data;

    }
}
