<?php

namespace App\Http\Controllers\Admin;

use App\Events\FloorPlanUploaded;
use App\Helpers\FloorPlanBody;
use App\Helpers\InsertSampleFloorPlan;
use App\Http\Services\AmenityService;
use App\Jobs\StoreFloorPlan;
use App\Models\File;
use App\Models\FloorPlan;
use App\Models\MappingTemplate;
use App\Models\Notice;
use App\Models\Property;
use App\Models\UnitTypeDetail;
use App\Rules\AdditionalFieldValidate;
use App\Rules\BaseUnitTypeRequired;
use App\Rules\RequiredCSVColumn;
use App\Rules\UniqueTemplatePerCompany;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\UploadedFile;
use League\Csv\Reader;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\AbstractHandler;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use Auth;
use Rap2hpoutre\FastExcel\FastExcel;
use Rap2hpoutre\FastExcel\SheetCollection;

class FloorPlanController extends Controller
{
    protected $table,$service,$floorPlan;
    public function __construct()
    {
        $this->service = new AmenityService();
        $this->floorPlan = new InsertSampleFloorPlan();

        $this->table = 'floor_plans';
        \View::share('page_title', 'Sqft Offset');
    }

    public function create($property_id)
    {
        $property = Property::find($property_id);
        $this->authorize('create', Property::class);
        $max_file_upload_size = $this->service->getSettingBySlug('max-file-upload-size');
        return view('admin.floor_plan.create',compact('max_file_upload_size','property'));

    }

    /**
     * Upload CSV File in Chunk
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws UploadMissingFileException
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Pion\Laravel\ChunkUpload\Exceptions\UploadFailedException
     */
    public function uploadCSV(Request $request)
    {
//        $this->authorize('create', Property::class);
        // create the file receiver
        $receiver = new FileReceiver("floor_plan_file", $request, HandlerFactory::classFromRequest($request));

        // check if the upload is success, throw exception or return response you need
        if ($receiver->isUploaded() === false) {
            throw new UploadMissingFileException();
        }

        // receive the file
        $save = $receiver->receive();

        // check if the upload has finished (in chunk mode it will send smaller files)
        if ($save->isFinished()) {
            // save the file and return any response you need, current example uses `move` function. If you are
            // not using move, you need to manually delete the file by unlink($save->getFile()->getPathname())
            return $this->storeCSV($save->getFile());
        }

        // we are in chunk mode, lets send the current progress
        /** @var AbstractHandler $handler */
        $handler = $save->handler();

        return response()->json([
            "done" => $handler->getPercentageDone(),
            'status' => true
        ]);
    }

    /**
     * Store Uploaded file to storage
     *
     * @param UploadedFile $file
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function storeCSV(UploadedFile $file)
    {
        $filename = date('Y_m_d_his').'_'.$file->getClientOriginalName();
        $filePath = "files/floor-plan/";
        $finalPath = storage_path("app/".$filePath);

        // move the file name
        $file->move($finalPath, $filename);

        return response()->json([
            'success' => true,
            'filename'=>$filename
        ]);
    }
    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \League\Csv\Exception
     */
    public function fieldMapping(Request $request)
    {
//        $this->authorize('create', Property::class);
        $required_header_arr = ['pms_unit_type', 'sqft', 'unit_type','unit_count'];


        /** Db header manually */
        $db_header = array(
            /** required starts */

            'pms_unit_type' => 'PMS Unit Type',
//            'pms_property' => 'Property Name',
            'sqft' => 'Square Feet',
            'unit_count' => 'Unit Count',
            'unit_type' => 'Unit Type',


            /** required ends */

            'pricing_offset' => 'Pricing Offset',
//            'floor_plan' => 'Floor Plan',
//            'description' => 'Description',
//            'beds' => 'Beds',
//            'baths' => 'Baths',

        );

        $header_row = $request->header_row;
        if(empty($header_row) || $header_row < 1){
            $header_row = 0;
        }else{
            $header_row = $header_row - 1;
        }

        $property_id = $request->property_id;
        $property = Property::find($property_id);
        // $path = $request->file('import_file')->getRealPath();
        $path = storage_path('app/files/floor-plan/').$request->filename;
        if (!ini_get("auto_detect_line_endings")) {
            ini_set("auto_detect_line_endings", TRUE);
        }
        $csv = Reader::createFromPath($path, 'r');
//        $csv->setOutputBOM(Reader::BOM_UTF8);
//        $csv->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');


//        $properties = Property::where('company_id',auth()->user()->company_id)->get();
        $csv->setHeaderOffset($header_row);
        $csv_header = $csv->getHeader();

        if(count(array_filter($csv_header)) != count($csv_header)) {
            return redirect('admin/floor_plan/create/property/'.$property_id)->with('error','There was an error with the uploaded file. Please check to make sure the top row has a header and no data is outside of the table range, then retry again.');
        }
        $sample_data = $csv->fetchOne($header_row);

//        $date1 = $date2 = true;
//        if(!empty($sample_data['Effective Date'])){
//            $date1 = $this->service->validateDate($sample_data['Effective Date']);
//        }
//        if(!empty($sample_data['Effective Date'])) {
//            $date2 = $this->service->validateDate($sample_data['Effective Date']);
//
//        }
//        if($date1 != true || $date2 != true){
//            return redirect('admin/property/create')->with('error','One Or More Column has invalid date. Please, format it first to yyyy-mm-dd format');
//        }


        $default_mapping = Array();
        $auth_user = \Auth::user();
        $query = MappingTemplate::where('table_name', $this->table)->where('saved','1');
        if(!$auth_user->hasRole('superAdmin')){
            $query->where('company_id',$auth_user->company_id);
        }
        $mapping_templates = $query->orderBy('template_name','asc')->get(); //list all the mapping templates on the select option
        if(!empty($request->mapping_template)){
            $mapping_template = MappingTemplate::where('id', $request->mapping_template)->first();
            $default_mapping = json_decode($mapping_template->map_data);
        }else{
            $mapping_template = new MappingTemplate();
        }
//        if($map_data){
//            $default_mapping = json_decode($map_data->map_data);
//        }


        $sample_data = array_values($sample_data);
        $filename = $request->filename;

        if (!ini_get("auto_detect_line_endings")) {
            ini_set('auto_detect_line_endings',FALSE);
        }

        return view('admin.floor_plan.import_fields', compact( 'csv_header', 'db_header','required_header_arr','default_mapping','sample_data','filename','mapping_templates','mapping_template','property','header_row'));

    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Illuminate\Validation\ValidationException
     * @throws \League\Csv\Exception
     */
    public function store(Request $request)
    {

        if($request->ajax()){

            $property = Property::findOrFail($request->property_id);

            $required_all = array('pms_unit_type', 'sqft', 'unit_type', 'unit_count');
            $rules = [
                'row' => [new RequiredCSVColumn($request->additional_field,$required_all)],
                'additional_field' => [new AdditionalFieldValidate()],
                'mapping_template_name' => ['required_with:saveMapping',new UniqueTemplatePerCompany($property->company_id)],
            ];

            $customMessages = [
                'mapping_template_name.required_with' => 'The Mapping Template Name cannot be blank (if you want to save it).',
//                'mapping_template_name.unique' => 'The Mapping Template Name is already used',
////                'mapping_template_name.required_without' => 'The Mapping Template Name is required',
//                'property_id.required' => 'Property is required',
//                'additional_field.*.csv_header.required_with' => 'CSV Header, Database Column and Sample value all are required for each new row',
//                'additional_field.*.row.required_with' => 'CSV Header, Database Column and Sample value all are required for each new row',
//                'additional_field.*.sample_value.required_with' => 'CSV Header, Database Column and Sample value all are required for each new row'
            ];

            $this->validate($request, $rules, $customMessages);

            $header_row = (int)$request->header_row;
            $row = $request->row;
            $auth_user = Auth::user();

            $csv_header = $request->csv_header;
            $additional_value = array();
            if(!empty($request->additional_field)){
                foreach($request->additional_field as $afk => $afv){
                    if(!empty($afv['csv_header'])&& !empty($afv['row']) && !empty($afv['sample_value'])) {
                        $csv_header[] = $afv['csv_header'];
                        $row[] = $afv['row'];
                        $additional_value[$afv['csv_header']] = $afv['sample_value'];
                    }
                }
            }

            $limit = $this->service->getSettingBySlug('chunk-limit');


            $data = [
                'user' => $auth_user,
                'url' => url()->current(),
                'row_value' => $header_row+2,
                'offset' => $header_row,
                'limit' => $limit,
                'file_name' => $request->filename,
                'map_data' => $row,
                'additional_value' => $additional_value,
                'property_id' => $request->property_id,
                'header_row' => $header_row
            ];


            $error_arr = array();
            $error_row_numbers = array();

            $resp = $this->floorPlan->insertSampleFloorPlanData($data,$error_arr,$error_row_numbers);
            if($resp['error']){
                $arr = array(
                    'errors' => [
                        'run_time_error' => [
                            $resp['error']
                        ]
                    ]
                );
                return response()->json($arr,422);
            }

//            if(!empty($request->mapping_template_id) && !isset($request->saveMapping)){
//                $map_template = MappingTemplate::findOrFail($request->mapping_template_id);
//                $mapping_template_id = $map_template->id;
//            }else{
//
//            }
                if(isset($request->mapping_template_name)){
                    $mapping_template_name = $request->mapping_template_name;
                    $saved = '1';
                }else{
                    $mapping_template_name = $property->property_name.'-'.date('Y_m_d_H_i_s');
                    $saved = '0';
                }
                $mapping_template = MappingTemplate::updateOrCreate(
                    [
                        'table_name' => $this->table,
                        'property_id' => $property->id,
                    ],
                    [
                        'template_name' => $mapping_template_name,
                        'csv_header' => json_encode($csv_header),
                        'map_data' => json_encode($row),
                        'company_id' => $property->company_id,
//                        'property_id' => $property->id,
                        'saved' => $saved,
                        'user_id' => $auth_user->id
                    ]
                );
                $mapping_template_id = $mapping_template->id;

            /** Store File Name */
            File::create([
                'filename' => $request->filename,
                'file_type' => 'sq',
                'property_id' => $property->id
            ]);

//            $data['mapping_template_id'] = $mapping_template_id;
//            Property::where('id', $data['property_id'])->update(['floor_file' => 2]);

            $propertyInsertJob = (new StoreFloorPlan($data,$error_arr,$error_row_numbers))->delay(Carbon::now()->addSeconds(3));
            dispatch($propertyInsertJob);

            $property->last_uploaded_at = date('Y-m-d H:i:s');
            $property->floor_file = 2;
            $property->save();

//            $text_body ='Hi '.\Auth::user()->name.',<br>Your file has been placed in queue for insertion. Once Completed, message will be sent to your email as well as in your dashboard mailbox.';
//
//            $slug = Notice::generateRandomSlug();
//            Notice::create([
//                'title' => $data['file_name'].' - CSV placed in queue',
//                'slug' => $slug,
//                'body' => json_encode($text_body),
//                'user_id' => \Auth::user()->id,
//                'property_id' => $data['property_id'],
//                'file_type' => '0',
//                'seen' => 'n'
//            ]);


//            event(new FloorPlanUploaded($request->property_id));
            $msg = [
                'status' => '1'
            ];
            echo json_encode($msg);
            exit();

        } //request ajax
    }

    public function index($property_id)
    {
        $floorPlan = FloorPlan::where('property_id',$property_id)->first();
        if(!$floorPlan){
            abort('404');
        }
        $property = Property::findOrFail($property_id);
        return view('admin.floor_plan.index',compact('property'));
    }

    public function getFloorPlanOffset(Request $request)
    {
        $records = FloorPlan::with(['unitTypeDetail'])->where('property_id',$request->property_id)->get();
        $unitTypeDetails = UnitTypeDetail::where('property_id',$request->property_id)->get();
        $property = Property::find($request->property_id);
//        $mapping_template = MappingTemplate::where('table_name','floor_plans')->where('property_id',$property->id)->first();
//        pe($mapping_template);
        $data = array();
        $data = [
            'records' => $records,
            'unitTypeDetails' => $unitTypeDetails,
            'property' => $property,
//            'map_data' => json_decode($mapping_template->map_data)
        ];
        $floorPlanBody = FloorPlanBody::getFloorPlanBody($data);
        return response()->json([
            'floorPlanBody' => $floorPlanBody,
        ]);

    }

//    public function updateRent(Request $request){
//
//        $request->validate([
//            'rate' => 'required|numeric|between:0,100',
//        ]);
//
//        foreach($request->unit_types as $uk => $uv){
//            if($uv != ''){
//                $uv = preg_replace('/[^\\d.]+/', '',$uv);
//            }
//            UnitTypeDetail::where('id',$uk)->update(['rent' => $uv]);
//        }
//
//        $property = Property::find($request->property_id);
//        $property->rate = preg_replace('/[^\\d.]+/', '',$request->rate);
//        $property->save();
//
//        $records = FloorPlan::with(['unitTypeDetail'])->where('property_id',$request->property_id)->get();
//
//        $data = [
//            'records' => $records,
//            'property' => $property
//        ];
//        $floorPlanBody = FloorPlanBody::getFloorPlanBody($data);
//        return response()->json([
//            'sts' => '1',
//            'floorPlanBody' => $floorPlanBody
//        ],200);
//
//    }

    public function saveFloorPlanOffset(Request $request)
    {

        $request->validate(
            [
                'rate' => 'required|numeric|between:0,100',
                'unit_types.*' => 'required|numeric',
                'radio_utid' => ['required', new BaseUnitTypeRequired(count($request->unit_types))],
            ],
            [
                'unit_types.*.required' => 'Unit Type\'s base rent is required.',
                'unit_types.*.numeric' => 'Value must be a number',
                'radio_utid.required' => 'Please select a base unit type for each grouping, then proceed.',
            ]
        );

        $property = Property::find($request->property_id);
        $property->rate = preg_replace('/[^\\d.]+/', '',$request->rate);
        $property->save();

        foreach($request->unit_types as $uk => $uv){
            if($uv != ''){
                $uv = preg_replace('/[^\\d.]+/', '',$uv);
            }
            UnitTypeDetail::where('id',$uk)->update(['rent' => $uv]);
        }

        if(!empty($request->radio_utid)) {
            foreach ($request->radio_utid as $rk => $rv) {
                $input_val = explode(":", $rv);
                UnitTypeDetail::where('id', $rk)
                    ->update(['base_floor_plan_id' => $input_val[0], 'base_sqft' => $input_val[1]]);
            }
        }
        $affodable_arr_list = $affordable_ids = array();
        foreach($request->affordable_arr as $aak => $aav){
            $affodable_arr_list[] = $aav;
        }

        if(!empty($request->affordable)) {
            foreach ($request->affordable as $ak => $av) {
                $affordable_ids[] = $av;
            };
        }
        $non_affordable_arr_ids = array_diff($affodable_arr_list,$affordable_ids);

        FloorPlan::whereIn('id', $non_affordable_arr_ids)->update(['affordable' => 0]);
        FloorPlan::whereIn('id', $affordable_ids)->update(['affordable' => 1]);

        $records = FloorPlan::with(['unitTypeDetail'])->where('property_id',$request->property_id)->get();
        $unitTypeDetails = UnitTypeDetail::where('property_id',$request->property_id)->get();

        $data = array();
        $data = [
            'records' => $records,
            'unitTypeDetails' => $unitTypeDetails,
            'property' => $property,
//            'map_data' => json_decode($mapping_template->map_data)
        ];
        $floorPlanBody = FloorPlanBody::getFloorPlanBody($data);

        return response()->json([
            'sts' => '1',
            'floorPlanBody' => $floorPlanBody,
        ],200);

    }

    public function export(Request $request)
    {

        $property = Property::find($request->property_id);
        $floorPlans = FloorPlan::with(['unitTypeDetail'])->where('property_id',$request->property_id)->get();
        $file_name = 'FloorPlan - '.$property->property_name.' - '.date('Y_m_d').'.xlsx';

        $mapping_template = MappingTemplate::where('table_name',$this->table)
            ->where('property_id',$property->id)
            ->first();
//        $to_export_columns = array();
//        $csv_headers = json_decode($mapping_template->csv_header);
        $mapped_columns = json_decode($mapping_template->map_data);
//        $count = count($mapped_column);

        $items = $unitTypes = array();

        $offset_data = $floorPlans->flatMap(function ($fp) use($property,$items,$mapped_columns) {
            $temp_items = array();
            if($fp->affordable == 1){
                $sqftDiff = '';
                $rpsf = '';
                $offset = '';
            }else{
                if($fp->unitTypeDetail->base_sqft == null || $fp->sqft == null){
                    $sqftDiff = '';
                }else{
                    $sqftDiff = $fp->sqft - $fp->unitTypeDetail->base_sqft;
                }

                if($property->rate  === null || $sqftDiff === '' || $fp->unitTypeDetail->base_sqft === null){
                    $offset = '';

                }else{
                    $brpsf =  ($fp->unitTypeDetail->rent / $fp->unitTypeDetail->base_sqft);
                    $offset = $sqftDiff * $brpsf * ($property->rate/100);
                    //                                    $round_offset = round($offset,2);
                }

                if($fp->unitTypeDetail->rent  == null || $fp->sqft == null){
                    $rpsf = '';
                }else{
                    if($offset == ''){
                        $rpsf = $fp->unitTypeDetail->rent / $fp->sqft;
                    }else{
                        $rpsf = ($fp->unitTypeDetail->rent + $offset) / $fp->sqft;
                    }

                }
            }

            if(in_array('pms_property',$mapped_columns)){
                $temp_items['PMS Property'] = $fp->pms_property;
            }
            $temp_items['PMS Unit Type'] = $fp->pms_unit_type;

            if(in_array('description',$mapped_columns)){
                $temp_items['Description'] = $fp->description;
            }
            if(in_array('beds',$mapped_columns)){
                $temp_items['Beds'] = $fp->beds;
            }
            if(in_array('baths',$mapped_columns)){
                $temp_items['Baths'] = $fp->baths;
            }
            $temp_items['Sqft'] = $fp->sqft;
            $temp_items['Unit Count'] = $fp->unit_count;

            $temp_items['Unit Type'] = $fp->unitTypeDetail->unit_type;
            $temp_items['Base'] = ($fp->unitTypeDetail->base_floor_plan_id == $fp->id)?'x':'';
            $temp_items['Affordable'] = ($fp->affordable == 1)?'x':'';
            $temp_items['SqFtDiff'] = $sqftDiff;
            $temp_items['RPSF'] = empty($rpsf)?'-':'$'.number_format(round($rpsf, 2),2);
            $temp_items['Offset'] = empty($offset)?'-':'$'.round($offset);

            $items[] = $temp_items;

            return $items;

        });

        $unitTypeDetails = UnitTypeDetail::where('property_id',$request->property_id)->get();

        $unitType_data = $unitTypeDetails->flatMap(function ($uv) use($unitTypes) {
            $unitTypes[] = [
                'Unit Type' => $uv->unit_type,
                'Rent' => $uv->rent
            ];
            return $unitTypes;
        });
        $unitType_data[] = [
            'Unit Type' => '',
            'Rent' => ''
        ];
        $unitType_data[] = [
            'Unit Type' => 'Rate',
            'Rent' => $property->rate.'%'
        ];

        $sheets = new SheetCollection([
            $offset_data,
            $unitType_data
        ]);
        return (new FastExcel($sheets))->download($file_name);

    }

}
