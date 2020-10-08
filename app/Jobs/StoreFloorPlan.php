<?php

namespace App\Jobs;

use App\Events\FloorPlanUploaded;
use App\Http\Services\AmenityService;
use App\Mail\CSVFailedJob;
use App\Mail\CSVImportJobCompleted;
use App\Mail\CSVImportJobPartiallyCompleted;

use App\Models\Notice;
use App\Models\Property;
use App\Models\UnitTypeDetail;
use Mail;

use App\Models\FloorPlan;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use League\Csv\Reader;
use League\Csv\Statement;

class StoreFloorPlan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $data,$error_arr,$error_row_numbers,$table, $service;

    /**
     * StoreFloorPlan constructor.
     * @param $data
     * @param $error_arr
     * @param $error_row_numbers
     */
    public function __construct($data,$error_arr,$error_row_numbers) {
        $this->data = $data;
        $this->error_arr = $error_arr;
        $this->error_row_numbers = $error_row_numbers;
        $this->table = 'floor_plans';
        $this->service = new AmenityService();
    }

    /**
     * Execute the job.
     *
     * @throws \League\Csv\Exception
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
        $dbase = new FloorPlan();

        $map_data = $this->data['map_data'];

        $db_header_obj = new FloorPlan();
//        $db_header = $db_header_obj->getTableColumns();

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

                $floorPlanArr = array();
                foreach ($map_data as $mk => $mv) {
                    if (isset($mv)) {
                        $floorPlanArr[$mv] = $cv[$mk];
                    }
                }
                $floorPlanArr['created_at'] = Carbon::now();
                $floorPlanArr['updated_at'] = Carbon::now();
                if (!array_key_exists('pricing_offset', $floorPlanArr)){
                    $floorPlanArr['pricing_offset'] = '';
                }
//                $properties_arr[] = $floorPlanArr;

                try{
                    $unitTypeDetail = UnitTypeDetail::firstOrCreate([
                        'unit_type' => $floorPlanArr['unit_type'],
                        'property_id' => $this->data['property_id']
                    ]);

                    $floorPlan = FloorPlan::firstOrCreate(
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
                            'property_id' => $this->data['property_id'],
                        ]
                    );
//                  Property::where('id',$this->data['property_id'])->update(['floor_file' => 2]);

                } catch (\Exception $e) {

                    if(isset($e->errorInfo)){
                        $error_arr = $e->errorInfo[2];
                        $this->error_arr[] = str_replace('at row 1', '', $error_arr);
                    }else{
                        $this->error_arr[] = $e->getMessage();
                    }

                    $this->error_row_numbers[] = $this->data['row_value'];
                }

                $this->data['row_value'] = $this->data['row_value'] + 1;
            }



            $this->data['offset'] = $offset + $limit;

//            $propertyInsertJob = (new StoreFloorPlan($this->data,$this->error_arr,$this->error_row_numbers))->delay(Carbon::now()->addSeconds(3));
//            dispatch($propertyInsertJob);
            StoreFloorPlan::dispatch($this->data,$this->error_arr,$this->error_row_numbers)->delay(Carbon::now()->addSeconds(3));

        }else{
            $arr_data = array();

            $err_count = count($this->error_arr);
            if($err_count > 0) {
                Property::where('id',$this->data['property_id'])->update(['floor_file' => 3]);
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
                        'file_type' => '2',
                    ],
                    [
                        'title' => 'File uploaded with errors',
                        'slug' => $slug,
                        'body' => json_encode($mailBody),
                        'user_id' => $this->data['user']->id,
                        'seen' => 'n'
                    ]
                );
                event(new FloorPlanUploaded($this->data['property_id']));
                if($this->data['user']->email_notification == 'on'){
                    Mail::to($this->data['user']->email)->send(new CSVImportJobPartiallyCompleted($arr_data));
                }
            }else{
                Property::where('id',$this->data['property_id'])->update(['floor_file' => 1]);
                $error = '';
                $array_from_to = array (
                    '[[USER_NAME]]' => $this->data['user']->name,
                    '[[FILENAME]]' => $filename,
                    '[[ERROR]]' => $error,
                );
                $mailBody = $this->service->formatMailBody('csv-insert-job-completed',$array_from_to);
                $arr_data['mailBody'] = $mailBody;
                event(new FloorPlanUploaded($this->data['property_id']));
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
