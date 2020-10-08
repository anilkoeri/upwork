<?php

namespace App\Jobs;

use App\Events\AmenityUploaded;
use App\Http\Services\AmenityService;
use App\Mail\CSVFailedJob;
use App\Mail\CSVImportJobCompleted;
use App\Mail\CSVImportJobPartiallyCompleted;
use App\Models\Amenity;
use App\Models\AmenityLevel;
use App\Models\AmenityValue;
use App\Models\Building;
use App\Models\Category;
use App\Models\Floor;
use App\Models\FloorGroup;
use App\Models\Notice;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitAmenityValue;
use Carbon\Carbon;
use DB,Mail;


use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use League\Csv\Reader;
use League\Csv\Statement;

class StoreProperty implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data,$error_arr,$error_row_numbers,$table,$service;

    /**
     * StoreProperty constructor.
     * @param $data
     * @param $error_arr
     * @param $error_row_numbers
     */
    public function __construct($data,$error_arr,$error_row_numbers) {
        $this->data = $data;
        $this->error_arr = $error_arr;
        $this->error_row_numbers = $error_row_numbers;
        $this->table = 'properties';
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
        $additional_property_ids = $this->data['additional_property_ids'];

        $filename = $this->data['file_name'];
        $service = new AmenityService();
        $dbase = new Property();

        // $skip = $this->data['skip'];
        $map_data = $this->data['map_data'];
//        $full_csv_header = $this->data['full_csv_header'];

        $db_header_obj = new Property();
        $db_header = $db_header_obj->getTableColumns();

        $csv_file_path = storage_path('app/files/property/').$filename;
        if (!ini_get("auto_detect_line_endings")) {
            ini_set("auto_detect_line_endings", TRUE);
        }
        $csv = Reader::createFromPath($csv_file_path, 'r');
        $csv->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');
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

                $property_arr = array();
                foreach ($map_data as $mk => $mv) {
                    if (isset($mv)) {
                            $property_arr[$mv] = $cv[$mk];
                    }
                }
                $property_arr['created_at'] = Carbon::now();
                $property_arr['updated_at'] = Carbon::now();

                try{
                    if($this->data['fs_col'] < 2 ) {
                        $unit_number = !empty($property_arr['unit_number']) ? trim($property_arr['unit_number']) : NULL;
                        if($unit_number == ''){
                            throw new \ErrorException('Unit Number is missing');
                        }
                        $splitted_unit_number = str_split($unit_number);
                        $str_len = strlen($unit_number);
                        $fsb_row = $this->data['fsb'][$str_len];
                        $building = $floor = $stack = '';
                        foreach ($fsb_row as $fk => $fv) {
                            if ($fv == 'building') {
                                $building .= $splitted_unit_number[$fk];
                            }
                            if ($fv == 'floor') {
                                $floor .= $splitted_unit_number[$fk];
                            }
                            if ($fv == 'stack') {
                                $stack .= $splitted_unit_number[$fk];
                            }
                        }
                        if($this->data['fs_col'] == 1){
                            $building = isset($property_arr['building_number'])?trim($property_arr['building_number']):'1';
                        }
                    }else{
                        $building = isset($property_arr['building_number'])?trim($property_arr['building_number']):'1';
                        $unit_number = !empty($property_arr['unit_number'])?trim($property_arr['unit_number']):NULL;
                        $floor = isset($property_arr['floor'])?trim($property_arr['floor']):NULL;
                        $stack = isset($property_arr['stack'])?trim($property_arr['stack']):NULL;
<<<<<<< HEAD

=======
>>>>>>> ff7d43ab61be04d9977890b28383ddd7ab3a621e
                    }
                    //create property
                    if(count($additional_property_ids) > 1){
                        $prop_id = array_search($property_arr['property'], $additional_property_ids);
                    }else{
                        $prop_id = $this->data['property_id'];
                    }
                    $property = Property::updateOrCreate(
                        ['id' => $prop_id],
                        [
                            'property_code' => isset($property_arr['property_code']) ? $property_arr['property_code'] : NULL,
                            'mapping_template_id' => $this->data['mapping_template_ids'][$prop_id],
                        ]
                    );

                    $building = Building::firstOrCreate(
                        [
                            'building_number' => !empty($building)?$building:'1',
                            'property_id' => $property->id,
                            'deleted_at' => NULL
                        ]
                    );
                    if(isset($property_arr['floor_plan_code'])) {
                        $floor_group = FloorGroup::firstOrCreate(
                            [
                                'floor_plan_code' => $property_arr['floor_plan_code'],
                                'deleted_at' => NULL
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
                            'floor_group_id' => isset($floor_group->id) ? $floor_group->id : NULL,
                            'deleted_at' => NULL
                        ]
                    );

                    $unit = Unit::firstOrCreate(
                        [
                            'unit_number' => $unit_number,
                            'floor_id' => $floor->id,
                            'building_id' => $building->id,
                            'deleted_at' => NULL
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
                    if (strlen($property_arr['amenity_name']) > 191) {
                        throw new \Exception('Data too long for Amenity Name. Max:191 characters');
                    }
                    $amenity = Amenity::firstOrCreate(
                        [
                            'amenity_name' => ($property_arr['amenity_name'] != '')?trim($property_arr['amenity_name']):NULL,
                            'category_id' => (isset($this->data['map_cat_arr'][$property_arr['amenity_name']]))?$this->data['map_cat_arr'][$property_arr['amenity_name']]:NULL,
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
                        ],
                        [
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
                            'amenity_value_id' => $amenity_value->id,
                            'deleted_at' => NULL
                        ]
                    );

                } catch (\Exception $e) {

                    if(isset($e->errorInfo)){
                        $error_arr = $e->errorInfo[2];
                        $this->error_arr[$prop_id][] = str_replace('at row 1', '', $error_arr);
                    }else{
                        $this->error_arr[$prop_id][] = $e->getMessage();
                    }

                    $this->error_row_numbers[$prop_id][] = $this->data['row_value'];
                }

                $this->data['row_value'] = $this->data['row_value'] + 1;
            }

            $this->data['offset'] = $offset + $limit;
//            $this->data['property_id'] = $property->id;

            $propertyInsertJob = (new StoreProperty($this->data,$this->error_arr,$this->error_row_numbers))->delay(Carbon::now()->addSeconds(1));
            dispatch($propertyInsertJob);

        }else{
            $arr_data = array();
            foreach($additional_property_ids as $ak => $av){
                if(isset($this->error_arr[$ak])){
                    $err_count = count($this->error_arr[$ak]);
                }else{
                    $err_count = 0;
                }
                if($err_count > 0){
                    Property::where('id',$ak)->update(['completed' => 3]);
                    $e_arr = $this->error_arr[$ak];
                    $e_row_numbers = $this->error_row_numbers[$ak];
                    $error = '';
                    if($err_count>0){
                        $error .= "Error occurred in following rows:<br/>";
                        for($i=0; $i<$err_count; $i++){
                            $error .= $e_row_numbers[$i].' ( '.$e_arr[$i].' ) ';
                            $error .="<br>";
                        }
                        $error .= "<hr>";
                    }
                    $array_from_to = array (
                        '[[USER_NAME]]' => $this->data['user_name'],
                        '[[FILENAME]]' => $filename,
                        '[[ERROR]]' => $error,
                    );
                    $mailBody = $this->service->formatMailBody('csv-insert-job-partially-completed',$array_from_to);
                    $arr_data['mailBody'] = $mailBody;
                    $slug = Notice::generateRandomSlug();
                    Notice::updateOrCreate(
                        [
                            'property_id' => $ak,
                            'file_type' => '1',
                        ],
                        [
                            'title' => 'File uploaded with errors',
                            'slug' => $slug,
                            'body' => json_encode($mailBody),
                            'user_id' => $this->data['user_id'],
                            'seen' => 'n'
                        ]
                    );
//                    event(new AmenityUploaded($this->data['property_id']));
                    if($this->data['email_notification'] == 'on') {
                        Mail::to($this->data['user_email'])->send(new CSVImportJobPartiallyCompleted($arr_data));
                    }
                }else{
                    Property::where('id',$ak)->update(['completed' => 1]);
                    $error = '';
                    $array_from_to = array (
                        '[[USER_NAME]]' => $this->data['user_name'],
                        '[[FILENAME]]' => $filename,
                        '[[ERROR]]' => $error,
                    );
                    $mailBody = $this->service->formatMailBody('csv-insert-job-completed',$array_from_to);
                    $arr_data['mailBody'] = $mailBody;
//                    event(new AmenityUploaded($p_ids));
                    if($this->data['email_notification'] == 'on') {
                        Mail::to($this->data['user_email'])->send(new CSVImportJobCompleted($arr_data));
                    }
                }
            }
            $p_ids = array_keys($additional_property_ids);
            event(new AmenityUploaded($p_ids));
        }

        if (!ini_get("auto_detect_line_endings")) {
            ini_set("auto_detect_line_endings", FALSE);
        }
    }

    /**
     * The job failed to process.
     *
     * @param \Exception $exception
     */
    public function failed(\Exception $exception)
    {
        $message = $exception->getMessage();
        $arr_data = [
            'filename' => $this->data['file_name'],
            'user_id' => $this->data['user_id'],
            'user_name' => $this->data['user_name'],
            'error_message' => $message,
        ];
        Mail::to($this->data['user_email'])->send(new CSVFailedJob($arr_data));

    }
}
