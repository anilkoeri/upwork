<?php

namespace App\Jobs;

use App\Events\APRUploaded;
use App\Http\Services\AmenityService;
use App\Mail\CSVImportJobCompleted;
use App\Mail\CSVImportJobPartiallyCompleted;
use App\Models\AmenityPricingReview;
use App\Models\Notice;
use App\Models\Property;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use League\Csv\Reader;
use League\Csv\Statement;

use App\Helpers\SanitizeData;

class StoreAPR implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data,$error_arr,$error_row_numbers,$table,$service;

    /**
     * Create a new job instance.
     * StoreAPR constructor.
     * @param $data
     * @param $error_arr
     * @param $error_row_numbers
     */
    public function __construct($data,$error_arr,$error_row_numbers)
    {
        $this->data = $data;
        $this->error_arr = $error_arr;
        $this->error_row_numbers = $error_row_numbers;
        $this->table = 'amenity_pricing_reviews';
        $this->service = new AmenityService();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
//        $result = 1/0;
        $header_row = $this->data['header_row'];
        $offset = $this->data['offset'];
        $limit = $this->data['limit'];
//        $limit = 5;
        $filename = $this->data['file_name'];
        $service = new AmenityService();
        $dbase = new AmenityPricingReview();

        $map_data = $this->data['map_data'];

        $db_header_obj = new AmenityPricingReview();
//        $db_header = $db_header_obj->getTableColumns();

        $csv_file_path = storage_path('app/files/amenity-pricing-review/').$filename;
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
//        $csv_header = $csv->getHeader();



        $rec_arr = array();
//        $properties_arr = array();

        $stmt = (new Statement())
            ->offset($offset)
            ->limit($limit)
        ;

        $records = $stmt->process($csv);


        foreach ($records as $record)
        {
            $record = array_merge($record, $this->data['additional_value']);
            $rec_arr[] = array_values($record);
        }

        $records_arr = $service->trimArray($rec_arr);

        if(count($records_arr)>0)
        {
            foreach($records_arr as $ck => $cv) {

                $pricingReviewArr = array();
                foreach ($map_data as $mk => $mv) {
                    if (isset($mv)) {
                        $pricingReviewArr[$mv] = $cv[$mk];
                    }
                }
                $pricingReviewArr['created_at'] = Carbon::now();
                $pricingReviewArr['updated_at'] = Carbon::now();

                $building_number = empty($pricingReviewArr['building_number'])?NULL:trim($pricingReviewArr['building_number']);
                if($building_number){
                    $building_id = array_search($building_number, $this->data['building_details']);
                }else{
                    $building_number = reset($this->data['building_details']);
                    $building_id = array_search($building_number, $this->data['building_details']);
                }

                $unit_number = empty($pricingReviewArr['unit_number'])?NULL:trim($pricingReviewArr['unit_number']);
                $unit_id = Unit::where('unit_number',$unit_number)->where('building_id',$building_id)->value('id');

                if(empty($unit_id)){
                    $this->error_arr[] = "Unit Number ".$pricingReviewArr['unit_number']." was not found for building ".$pricingReviewArr['building_number'];
                    $this->error_row_numbers[] = $this->data['row_value'];
                }else {
                    try {
                        AmenityPricingReview::firstOrCreate(
                            [
                                'resident_id' => empty($pricingReviewArr['resident_id']) ? NULL : trim($pricingReviewArr['resident_id']),
                                'unit_id' => empty($unit_id) ? NULL : $unit_id,
                                'building_id' => $building_id,
                                'property_id' => $this->data['property_id']
                            ],
                            [
                                'community_name' => isset($pricingReviewArr['community_name']) ? SanitizeData::formatNull($pricingReviewArr['community_name']) : NULL,
                                'application_date' => isset($pricingReviewArr['application_date']) ? SanitizeData::formatDate($pricingReviewArr['application_date']) : NULL,
                                'dom' => isset($pricingReviewArr['dom']) ? SanitizeData::formatNull($pricingReviewArr['dom']) : NULL,
                                'days_vacant' => isset($pricingReviewArr['days_vacant']) ? SanitizeData::formatNull($pricingReviewArr['days_vacant']) : NULL,
                                'move_in_date' => isset($pricingReviewArr['move_in_date']) ? SanitizeData::formatDate($pricingReviewArr['move_in_date']) : NULL,
                                'lease_end_date' => isset($pricingReviewArr['lease_end_date']) ? SanitizeData::formatDate($pricingReviewArr['lease_end_date']) : NULL,
                                'notice_date' => isset($pricingReviewArr['notice_date']) ? SanitizeData::formatDate($pricingReviewArr['notice_date']) : NULL,
                                'move_out_date' => isset($pricingReviewArr['move_out_date']) ? SanitizeData::formatDate($pricingReviewArr['move_out_date']) : NULL,
                                'previous_notice_date' => isset($pricingReviewArr['previous_notice_date']) ? SanitizeData::formatDate($pricingReviewArr['previous_notice_date']) : NULL,
                                'previous_move_out_date' => isset($pricingReviewArr['previous_move_out_date']) ? SanitizeData::formatDate($pricingReviewArr['previous_move_out_date']) : NULL
                            ]
                        );

                    } catch (\Exception $e) {

                        if (isset($e->errorInfo)) {
                            $error_arr = $e->errorInfo[2];
                            $this->error_arr[] = str_replace('at row 1', '', $error_arr);
                        } else {
                            $this->error_arr[] = $e->getMessage();
                        }

                        $this->error_row_numbers[] = $this->data['row_value'];
                    }
                }

                $this->data['row_value'] = $this->data['row_value'] + 1;
            }



            $this->data['offset'] = $offset + $limit;

            $propertyInsertJob = (new StoreAPR($this->data,$this->error_arr,$this->error_row_numbers))->delay(Carbon::now()->addSeconds(3));
            dispatch($propertyInsertJob);

        }else{
            $arr_data = array();
//            $arr_data = [
//                'property_id' => $this->data['property_id'],
//                'filename' => $filename,
//                'file_type' => '2',
//                'user_id' => $this->data['user']->id,
//                'user_name' => $this->data['user']->name,
//                'error' => $this->error_arr,
//                'error_row_numbers' => $this->error_row_numbers
//            ];
            // DB::statement('EXEC procUpdateSortBaseVoterSubId');
//            Mail::to($this->data['user_email'])->send(new CSVImportJobCompleted($arr_data));
            $err_count = count($this->error_arr);
            if($err_count > 0) {
                Property::where('id',$this->data['property_id'])->update(['pricing_review' => 3]);
                $error = '';
                if($err_count>0){
                    $error .= "Error occurred in following rows:<br/>";
                    for($i=0; $i<$err_count; $i++){
                        $error .= $this->error_row_numbers[$i].' ( '.$this->error_arr[$i].' ) ';
                        $error .="<br>";
                    }
                    $error .= "<hr>";
                }
                $array_from_to = array (
                    '[[USER_NAME]]' => $this->data['user']->name,
                    '[[FILENAME]]' => $filename,
                    '[[ERROR]]' => $error,
                );
                $mailBody = $this->service->formatMailBody('csv-insert-job-partially-completed',$array_from_to);
                $arr_data['mailBody'] = $mailBody;
                $slug = Notice::generateRandomSlug();
                Notice::updateOrCreate(
                    [
                        'property_id' => $this->data['property_id'],
                        'file_type' => '3',
                    ],
                    [
                        'title' => 'File uploaded with errors',
                        'slug' => $slug,
                        'body' => json_encode($mailBody),
                        'user_id' => $this->data['user']->id,
                        'seen' => 'n'
                    ]
                );
                event(new APRUploaded($this->data['property_id']));
                if($this->data['user']->email_notification == 'on'){
                    Mail::to($this->data['user']->email)->send(new CSVImportJobPartiallyCompleted($arr_data));
                }
            }else{
                Property::where('id',$this->data['property_id'])->update(['pricing_review' => 1]);
                $error = '';
                $array_from_to = array (
                    '[[USER_NAME]]' => $this->data['user']->name,
                    '[[FILENAME]]' => $filename,
                    '[[ERROR]]' => $error,
                );
                $mailBody = $this->service->formatMailBody('csv-insert-job-completed',$array_from_to);
                $arr_data['mailBody'] = $mailBody;
                event(new APRUploaded($this->data['property_id']));
                if($this->data['user']->email_notification == 'on'){
                    Mail::to($this->data['user']->email)->send(new CSVImportJobCompleted($arr_data));
                }
            }
        }

        if (!ini_get("auto_detect_line_endings")) {
            ini_set("auto_detect_line_endings", FALSE);
        }
    }
}
