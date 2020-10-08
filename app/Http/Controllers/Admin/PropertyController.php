<?php

namespace App\Http\Controllers\Admin;

use App\Events\AmenityUploaded;
use App\Http\Services\AmenityService;
use App\Jobs\StoreProperty;
use App\Models\Amenity;
use App\Models\AmenityCategoryMapping;
use App\Models\AmenityPricingReview;
use App\Models\AmenityValue;
use App\Models\Building;
use App\Models\Category;
use App\Models\Company;
use App\Models\File;
use App\Models\FloorPlan;
use App\Models\MappingTemplate;
use App\Models\Notice;
use App\Models\Property;
use App\Models\UnitTypeDetail;
use App\Rules\AdditionalFieldValidate;
use App\Rules\AtLeastOneLetter;
use App\Rules\CheckIfArrayItemIsEmpty;
use App\Rules\EitherBothOrNone;
use App\Rules\RequiredCSVColumn;
use App\Rules\UniquePropertyPerCompany;
use App\Rules\UniqueTemplatePerCompany;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use Auth,Carbon\Carbon;

/**League\CSV*/

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Statement;

/**php chunk upload*/
use Illuminate\Http\UploadedFile;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\AbstractHandler;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;

use DB;

class PropertyController extends Controller
{
    protected $table,$service;
    public function __construct()
    {
        $this->service = new AmenityService();
        $this->table = 'properties';
        \View::share('page_title', 'Property');
    }
    /**
     * Display a listing of the resource.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->authorize('view', Property::class);
        $companies = Company::all();
        return view('admin.property.index',compact('companies'));
    }

    /**
     * Show the form for creating a new resource.
     * @param $property_id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function create($property_id)
    {
        $this->authorize('create', Property::class);
        $property = Property::find($property_id);
        $max_file_upload_size = $this->service->getSettingBySlug('max-file-upload-size');
        return view('admin.property.create',compact('max_file_upload_size','property'));
    }

    public function storeProperty(Request $request)
    {
        $request->validate([
            'company' => 'required',
            'property_name' => ['required','max:191',new UniquePropertyPerCompany($request->company)]
        ]);
        $property = Property::create([
            'property_name' =>  $request->property_name,
            'company_id' => $request->company
        ]);

        return response()->json([
            'sts' => '1',
            'property' => $property,
            'company' => $property->company->name,
            'amenity_review' => \View::make('admin.property._amenityReview')->with('r',$property)->render(),
            'sqft_offset_review' => \View::make('admin.property._sqftOffsetColumn')->with('r',$property)->render(),
            'pricing_review' => \View::make('admin.property._pricingReviewColumn')->with('r',$property)->render(),
            'creaetd_at' => $property->created_at
        ]);
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
        $this->authorize('create', Property::class);

        if($request->ajax()){
            $fieldMappingDetails = json_decode($request->fieldMappingDetails);
            $property = Property::findOrFail($request->property_id);
            $additional_rows = array();
            foreach($fieldMappingDetails->additional_field as $ak => $av){
                $additional_rows[] = $av->row;
            }
            $row = $fieldMappingDetails->row;
            $auth_user = Auth::user();
            $header_row = (int)$request->header_row;
            $csv_header = $fieldMappingDetails->csv_header;
            $additional_property_ids = (array)json_decode($request->additional_property_ids);
            if(empty($additional_property_ids)){
                $additional_property_ids[$property->id] = $property->property_name;
                $multiple_properties = false;
            }else{
                $multiple_properties = true;
            }
            $additional_value = $mapping_template_ids = array();
            if(!empty($request->additional_field)){
                foreach($request->additional_field as $afk => $afv){
                    if(!empty($afv['csv_header'])&& !empty($afv['row']) && !empty($afv['sample_value'])) {
                        $csv_header[] = $afv['csv_header'];
                        $row[] = $afv['row'];
                        $additional_value[$afv['csv_header']] = $afv['sample_value'];
                    }
                }
            }

            $amenities_arr = json_decode($request->amenities_arr, true);
            $cnt = count($amenities_arr);
            $fs_col = $request->fs_col;

            if($fs_col > 1) {
                $cat_map = $request->cat_map;
                $fsb = [];
            }else{
                $cat_map = json_decode($request->cat_map);
                $fsb = json_decode($request->fsb);
            }

            foreach($cat_map as $ck => $cv){
                if(!is_numeric($cv)){
                    $new_cat = Category::firstOrCreate(
                        [
                            'category_name' => $cv,
                            'company_id' => ($multiple_properties)?NULL:$property->company_id,
                            'property_id' => $property->id,
                        ],
                        [
                            'global' => 0
                        ]);
                    $cat_map[$ck] = $new_cat->id;
                }
            }

            $map_cat_arr = array();
            for ($i = 0; $i < $cnt; $i++) {
                $map_cat_arr[$amenities_arr[$i]] = $cat_map[$i];
            }

            $limit = $this->service->getSettingBySlug('chunk-limit');
//            $offset = $row_value - 2;
            $data = [
                'user_id' => $auth_user->id,
                'user_email' => $auth_user->email,
                'user_name' => $auth_user->name,
                'email_notification' => $auth_user->email_notification,
                'url' => url()->current(),
                'row_value' => $header_row+2,
                'offset' => $header_row,
                'limit' => $limit,
                'file_name' => $request->filename,
                'map_data' => $row,
                'additional_value' => $additional_value,
                'property_id' => $request->property_id,
                'map_cat_arr' => $map_cat_arr,
                'fs_col' => $fs_col,
                'fsb' => $fsb,
                'header_row' => $header_row,
                'additional_property_ids' => $additional_property_ids
            ];
            $error_arr = array();
            $error_row_numbers = array();

            $resp = $this->service->insertSampleData($data,$error_arr,$error_row_numbers);
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

            $insert_cat = array();
            foreach($map_cat_arr as $mk => $mv){
                $mk = trim($mk);
                $query = AmenityCategoryMapping::where('amenity_name',$mk)
                    ->where('category_id',$mv);
                $query->where(function($query) use($property){
                    $query->whereNull('property_id')
//                        ->orWhere('company_id',$property->company_id);
                        ->orWhere('property_id',$property->id);
                });
                $res = $query->first();
                if(!$res){
                    if (strlen($mk) < 191) {
                        AmenityCategoryMapping::updateOrCreate(
                            [
                                'amenity_name' => strtolower($mk),
                                'property_id' => ($multiple_properties) ? NULL : $property->id
                            ],
                            [
                                'company_id' => $property->company_id,
                                'category_id' => $mv,
                                'updated_at' => date('Y-m-d H:i:s'),
                                'deleted_at' => NULL
                            ]
                        );
                    }
                }

            }

            foreach($additional_property_ids as $ak => $av){
                if(!empty($fieldMappingDetails->mapping_template_name)){
                    $mapping_template_name = $fieldMappingDetails->mapping_template_name;
                    $saved = '1';
                }else{
                    $mapping_template_name = $av.'-'.date('Y_m_d_H_i_s');
                    $saved = '0';
                }
                $mapping_template = MappingTemplate::updateOrCreate(
                    [
                        'table_name' => $this->table,
                        'property_id' => $ak,
                    ],
                    [
                        'template_name' => $mapping_template_name,
                        'table_name' => $this->table,
                        'csv_header' => json_encode($csv_header),
                        'map_data' => json_encode($row),
                        'company_id' => $property->company_id,
                        'saved' => $saved,
                        'user_id' => $auth_user->id
                    ]
                );
                $mapping_template_ids[$ak] = $mapping_template->id;

            }

            /** Store File Name */
            File::create([
                'filename' => $request->filename,
                'file_type' => 'p',
                'property_id' => $property->id
            ]);

            $data['mapping_template_ids'] = $mapping_template_ids;

//            Property::where('id', $data['property_id'])->update(['completed' => 2]);
            $propertyInsertJob = (new StoreProperty($data,$error_arr,$error_row_numbers))->delay(Carbon::now()->addSeconds(3));
            dispatch($propertyInsertJob);
            foreach($additional_property_ids as $ak => $av){
                $p = Property::findOrFail($ak);
                $p->last_uploaded_at = date('Y-m-d H:i:s');
                $p->completed = 2;
                $p->save();
            }

            return response()->json([
                'sts' => '1'
            ],200);

        } //request ajax
    }

    /**
     * Display the specified resource.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $this->authorize('view', Property::class);
        $buildings = Building::where('property_id',$id)->get();
        $categories = Category::get(['id','category_name']);
        $property_id = $id;
        return view('admin.property.show',compact('buildings','categories', 'property_id'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $property = Property::findOrFail($id);
        return response()->json([
            'property' => $property
        ],200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'property_name' => ['required','max:191',new UniquePropertyPerCompany($request->company,$id)]
        ]);

        $property = Property::find($id);
        $property->property_name = $request->property_name;
        $property->save();
        return response()->json([
            'property' => $property
        ],200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->authorize('destroy', Property::class);

        $property = Property::findOrFail($id);
        $property->delete();

        return response()->json([
            'message' => 'Successfully Deleted'
        ], 200);

//        return redirect('admin/property')->with('success','Successfully Deleted');
    }

    /**
     * Return limited lists in json Format.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException  if user is not authorized
     * @param  \Illuminate\Http\Request  $request
     * @return string JSON
     */
    public function list(Request $request)
    {
        $this->authorize('view', Property::class);
        $columns = array(
            0 => 'companies.name',
            1 => 'properties.property_name',
            2 => 'pricing_review',
            3 => 'amenity_review',
            4 => 'sqft_offset_review',
            5 => 'properties.created_at',
            6 => 'properties.last_uploaded_at'
        );

        if(\Auth::user()->hasRole('superAdmin')){
            $totalData = Property::count();
        }else{
            $totalData = Property::where('company_id',\Auth::user()->company_id)->count();
        }

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $query = Property::join('companies', 'companies.id', 'properties.company_id') // or leftJoin
            ->with(['notices']);

        $company_search = $request->columns[0]['search']['value'];
        if(!empty($company_search)){
                $query->where('companies.name','like','%'.$company_search.'%');
        }
        $property_search = $request->columns[1]['search']['value'];
        if(!empty($property_search)){
            $query->where('properties.property_name','like','%'.$property_search.'%');
        }
        if(!Auth::user()->hasRole('superAdmin')) {
            $query->where('company_id',Auth::user()->company_id);
        }

        $query->orderBy($order,$dir);

        if($limit != '-1'){
            $records = $query->offset($start)->limit($limit);
        }
        $records = $query->get(['properties.*','companies.name']);

        $totalFiltered = $totalData;
        $data = array();

        if($records){

            foreach($records as $r){
                $nestedData['id'] = $r->id;
                $nestedData['company_name'] = $r->name;
                $nestedData['property_name'] = '<span id="pName_'.$r->id.'">'.$r->property_name.'</span>'.\View::make('admin.property.action')->with('r',$r)->render();
                $nestedData['amenity_review'] = \View::make('admin.property._amenityReview')->with('r',$r)->render();
                $nestedData['sqft_offset_review'] = \View::make('admin.property._sqftOffsetColumn')->with('r',$r)->render();
                $nestedData['pricing_review'] = \View::make('admin.property._pricingReviewColumn')->with('r',$r)->render();
                $nestedData['created_at'] = $r->created_at;
                $nestedData['last_uploaded_at'] = $r->last_uploaded_at;
                $data[] = $nestedData;
            }
        }

        $json_data = array(
            "draw"          => intval($request->input('draw')),
            "recordsTotal"  => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"          => $data
        );
        echo json_encode($json_data);
        exit();

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
        $this->authorize('create', Property::class);
        // create the file receiver
        $receiver = new FileReceiver("import_file", $request, HandlerFactory::classFromRequest($request));

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
        $this->authorize('create', Property::class);
        // pe($file->getClientOriginalName());
        // $extension = $file->getClientOriginalExtension();
        // $filename = uniqid().'_'.time().'_'.date('Ymd').'.'.$extension;
        $filename = date('Y_m_d_his').'_'.$file->getClientOriginalName();
        // Build the file path
        $filePath = "files/property/";
        $finalPath = storage_path("app/".$filePath);

        // move the file name
        $file->move($finalPath, $filename);


//        $arr = [
//            'action' => 'storeCSV',
//            'table' => $this->table,
//        ];
//        $this->storeActivity($arr);

        return response()->json([
            'success' => true,
            'filename'=>$filename
        ]);
    }

    /**
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \League\Csv\Exception
     */
    public function fieldMapping(Request $request)
    {
        $required_header_arr = ['amenity_name', 'amenity_value', 'floor', 'stack', 'unit_number'];
        $header_row = $request->header_row;
        if(empty($header_row) || $header_row < 1){
            $header_row = 0;
        }else{
            $header_row = $header_row - 1;
        }
        $property = Property::find($request->property_id);

        $path = storage_path('app/files/property/').$request->filename;
        if (!ini_get("auto_detect_line_endings")) {
            ini_set("auto_detect_line_endings", TRUE);
        }
        $csv = Reader::createFromPath($path, 'r');
        $csv->skipEmptyRecords();

//        $csv->setOutputBOM(Reader::BOM_UTF8);
//        $csv->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');

        $csv->setHeaderOffset($header_row);
        $csv_header = $csv->getHeader();
        if(count(array_filter($csv_header)) != count($csv_header)) {
            return redirect('admin/property/create/'.$property->id)->with('error','There was an error with the uploaded file. Please check to make sure the top row has a header and no data is outside of the table range, then retry again.');
        }

        //if csv_header has floor/floors or stack/stacks add floor and stack on mapping
        if(!empty(array_intersect(
            ['floor','floors','stack','stacks'],
            array_map(function($v){
                $v = strtolower(trim($v));
                return $v;
            },$csv_header)
        ))){
            $db_header = array(
                /** required starts */
                'amenity_name' => 'Amenity Name',
                'amenity_value' => 'Amenity Value',
                'building_number' => 'Building Number',
                'floor' => 'Floor',
                'stack' => 'Stack',
                'unit_number' => 'Unit Number',
                /** required ends */
            );
            $fs_col = true;
        }else{
            $db_header = array(
                /** required starts */
                'amenity_name' => 'Amenity Name',
                'amenity_value' => 'Amenity Value',
                'building_number' => 'Building Number',
                'unit_number' => 'Unit Number',
                /** required ends */
            );
            $fs_col = false;
        }

        //if csv header has property / properties
        if(!empty(array_intersect(
            ['property','properties'],
            array_map(function($v){
                $v = strtolower(trim($v));
                return $v;
            },$csv_header)
        ))){
            $db_header['property'] = 'Property';
        }

        $sample_data = $csv->fetchOne($header_row);
        $default_mapping = Array();
        $auth_user = \Auth::user();

        $query = MappingTemplate::where('table_name', $this->table)->where('saved','1');
        if(!$auth_user->hasRole('superAdmin')){
            $query->where('company_id',$auth_user->company_id);
        }
        $mapping_templates = $query->orderBy('template_name','asc')->get(); //list all the mapping templates on the select option

        //check if there is any selected option (if yes set this option as selected and map as it is)
        if(!empty($request->mapping_template)){
            $mapping_template = MappingTemplate::where('id', $request->mapping_template)->first();
            $default_mapping = json_decode($mapping_template->map_data);
        }else{
            $mapping_template = new MappingTemplate();
        }

        $sample_data = array_values($sample_data);
        $filename = $request->filename;

        if (!ini_get("auto_detect_line_endings")) {
            ini_set('auto_detect_line_endings',FALSE);
        }

        return view('admin.property.import_fields', compact( 'csv_header', 'db_header','required_header_arr','default_mapping','sample_data','filename','mapping_templates','mapping_template','property','fs_col','header_row'));

    }


    /**
     * Return list of buildings of a property
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBuildingsByProperty($id)
    {
        $buildings = Building::where('property_id',$id)->get();

        return response()->json([
            'buildings' => $buildings,
        ],200);
    }

    public function mapCategory(Request $request)
    {

        if ($request->ajax()) {
            $property = Property::find($request->property_id);
            $additional_property_ids = array();
            $required_all = array('amenity_name','amenity_value','unit_number');
            $required_all_or_none = array('floor','stack');
            $rules = [
//                'additional_field.*.csv_header' => 'required_with:additional_field.*.row,required_with:additional_field.*.sample_value',
//                'additional_field.*.row' => 'required_with:additional_field.*.csv_header,required_with:additional_field.*.sample_value',
//                'additional_field.*.sample_value' => 'required_with:additional_field.*.csv_header,required_with:additional_field.*.row',
                'row' => [new RequiredCSVColumn($request->additional_field,$required_all), new EitherBothOrNone($request->additional_field,$required_all_or_none)],
                'additional_field' => [new AdditionalFieldValidate()],
                'mapping_template_name' => ['required_with:saveMapping', new UniqueTemplatePerCompany($property->company_id)],
//                'required_without:mapping_template_id'

            ];

            $customMessages = [
                'mapping_template_name.required_with' => 'The Mapping Template Name cannot be blank (if you want to save it).',
                //                'mapping_template_name.unique' => 'The Mapping Template Name is already used',
                'mapping_template_name.required_without' => 'The Mapping Template Name is required',
//                'additional_field.*.csv_header.required_with' => 'CSV Header, Database Column and Sample Value all are required with added row',
//                'additional_field.*.row.required_with' => 'CSV Header, Database Column and Sample Value all are required with added row',
//                'additional_field.*.sample_value.required_with' => 'CSV Header, Database Column and Sample Value all are required with added row'
            ];

            $this->validate($request, $rules, $customMessages);
            $fs_count = count(array_intersect(['floor','stack'], $request->row));
            if($fs_count === 2){
                if(in_array('building_number',$request->row)){
                    $fs_col = 3; //floor, stack, building
                }else{
                    $fs_col = 2; //just floor, stack
                }
            }else{
                if(in_array('building_number',$request->row)){
                    $fs_col = 1; //building only
                }else{
                    $fs_col = 0; //none
                }
            }
            $header_row = (int)$request->header_row;
            $fieldMappingDetails = json_encode($request->except('_token', 'filename', 'property_id','fsb','header_row'));
            $filename = $request->filename;

            $path = storage_path('app/files/property/') . $request->filename;
            if (!ini_get("auto_detect_line_endings")) {
                ini_set("auto_detect_line_endings", TRUE);
            }
            $csv = Reader::createFromPath($path, 'r');
            $csv->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');
            $csv->skipEmptyRecords();
            $csv->setHeaderOffset($header_row);
            $csv_header = $csv->getHeader();

            $amenity_key = array_search('amenity_name', $request->row);
            $result = $csv->fetchColumn($amenity_key);
            $amenities_list = iterator_to_array($result, false);
            while(end($amenities_list) == ''){
                array_pop($amenities_list);
            }
            if($header_row > 0){
                $amenities_list = array_slice($amenities_list,$header_row);
            }
            $amenities = array_unique($amenities_list);
            natcasesort($amenities);
            foreach($amenities as $ak => $av){
                if($av == ""){
                    unset($amenities[$ak]);
//                    array_push($amenities, $av);
                    break;
                }
            }
            $amenities = array_values($amenities);

            /**unit_number **/
            $unit_number_key = array_search('unit_number', $request->row);
            $unit_number_results = $csv->fetchColumn($unit_number_key);
            $unit_number_lists = iterator_to_array($unit_number_results, false);
            if($header_row > 0){
                $unit_number_lists = array_slice($unit_number_lists,$header_row);
            }
            $unit_numbers = array_filter(array_unique($unit_number_lists));
            $unitNumbers = $amenities_lower = array();
            foreach($unit_numbers as $uk => $uv){
                $r_trimmed_uv = rtrim($uv);
                $str_len = strlen($r_trimmed_uv);
                if(!isset($unitNumbers[$str_len])){
                    $unitNumbers[$str_len] = $r_trimmed_uv;
                }
            }
            ksort($unitNumbers);
//            if(count(array_filter($unitNumbers)) != count($unitNumbers)) {
//                return redirect('admin/property/create/'.$property->id)->with('error','');
//            }

            //if multiple properties extract them
            if(in_array('property',$request->row)){
                $property_key = array_search('property', $request->row);
                $result = $csv->fetchColumn($property_key);
                $properties_list = iterator_to_array($result, false);
                while(end($properties_list) == ''){
                    array_pop($properties_list);
                }
                if($header_row > 0){
                    $properties_list = array_slice($properties_list,$header_row);
                }
                $properties = array_unique($properties_list);
                $properties = array_values($properties);
                foreach($properties as $ak => $av){
                    if($av == ""){
                        unset($properties[$ak]);
                        break;
                    }
                    if($ak == 0 ){
                        $additional_property_ids[$property->id] = $av;
                    }else{
                        $np = Property::where('property_name',$av)
                            ->where('company_id',$property->company_id)
                            ->first();
                        if($np){
                            $np->updated_at     = date('Y-m-d H:i:s');
                            $np->save();
                        }else{
                            $np                 = new Property();
                            $np->property_name  = $av;
                            $np->company_id     = $property->company_id;
                            $np->created_at     = date('Y-m-d H:i:s');
                            $np->updated_at     = date('Y-m-d H:i:s');
                            $np->save();
                        }
                        $additional_property_ids[$np->id] = $av;
                    }
                }
            }

            $categories = Category::where('global', 1)
                ->orWhere('company_id', $property->company_id)
                ->orderBy('global', 'desc')
                ->orderBy('category_name', 'asc')
                ->get()
                ->toArray();
//            dd($categories);

            $amenityCategoryMapping1 = AmenityCategoryMapping::where('property_id', $property->id)
                ->orderBy('amenity_name','asc')
                ->orderBy('updated_at','desc')
                ->pluck('category_id', 'amenity_name')->toArray();

            $amenityCategoryMapping2 = AmenityCategoryMapping::where('company_id', $property->company_id)
                ->orderBy('amenity_name','asc')
                ->orderBy('updated_at','desc')
                ->pluck('category_id', 'amenity_name')->toArray();

            $amenityCategoryMapping3 = AmenityCategoryMapping::whereNull('property_id')
                ->whereNull('company_id')
                ->orderBy('amenity_name','asc')
                ->orderBy('updated_at','desc')
                ->pluck('category_id', 'amenity_name')->toArray();

            $amenityCategoryMapping = array_merge($amenityCategoryMapping3, $amenityCategoryMapping2, $amenityCategoryMapping1 );

//            $amenityCategoryMappings = AmenityCategoryMapping::whereNull('property_id')
//                ->orWhere('property_id', $property->id)
//                ->orWhere('company_id', $property->company_id)
//                ->orderBy('amenity_name','asc')
//                ->orderBy('company_id','desc')
//                ->orderBy('updated_at','desc')
//                ->get();

//            $property_specific_amenities_array = array();
//            foreach($amenityCategoryMappings as $ack => $acv){
//                //check if the mapping is global (i.e no property_id),
//                //if it is no global add amenity_name to property specific
//                //if it is global but there is also a property specific mapping remove that global mapping
//                if(empty($acv->property_id)){
//                    if(in_array($acv->amenity_name,$property_specific_amenities_array)){
//                        unset($amenityCategoryMappings[$ack]);
//                    }
//                }else{
//                    $property_specific_amenities_array[] = $acv->amenity_name;
//                }
//            }
//            $amenityCategoryMapping = $amenityCategoryMappings->pluck('category_id', 'amenity_name')->toArray();
            $mapped_array = $same_arr = array();

            $acm_keys = array_keys($amenityCategoryMapping);
            $amenities_lower = array_map(function($v){
                $v = trim($v);
                $v = strtolower($v);
                return $v;
                }, $amenities);
//            $amenities_lower = array_map('strtolower', $amenities);
            $same_arr = array_intersect($amenities_lower,$acm_keys);
            foreach($same_arr as $sk => $sv){
                //mapping which keywords are matched exactly
                $mapped_array[$sk] = isset($amenityCategoryMapping[$sv])?$amenityCategoryMapping[$sv]:'';
            }
//            pe($mapped_array);
            //if all the amenites does not found the exact match in amenity category mapping
            if(count($mapped_array) != count($amenities)){
                foreach($amenities_lower as $ak => $av){
                    //check if the category of a amenity is already found or not
                    if(!array_key_exists($ak,$mapped_array)){
                        //$av == trim($av) &&
                        if (strpos($av, ' ') !== false) {
                            $subs = explode(' ', $av);
                            $same_arr2 = array_intersect($subs,$acm_keys);
                            $cnt = count($same_arr2);
                            if($same_arr2){
//                                if(count($same_arr2) == 1){
//                                    $key_n = array_pop($same_arr2);
//                                    $mapped_array[$ak] = $amenityCategoryMapping[$key_n];
//                                }else{
                                    $arr_val = array();
                                    foreach($same_arr2 as $sk2 => $sv2){
                                        $arr_val[] = $amenityCategoryMapping[$sv2];
                                    }
                                    if(count(array_unique($arr_val)) == 1){
                                        $mapped_array[$ak] = $arr_val[0];
                                    }
//                                }
                            }
                        }
                    }
                    if(!isset($mapped_array[$ak])){
                        $mapped_array[$ak] = '';
                    }

                }
            }
            ksort($mapped_array);
            return response()->json([
                'success' => 1,
                'amenities' => json_encode($amenities),
                'categories' => json_encode($categories),
                'filename' => $filename,
                'property' => json_encode($property),
                'amenityCategoryMapping' => json_encode($amenityCategoryMapping),
                'mapped_array' => json_encode($mapped_array),
                'fieldMappingDetails' => $fieldMappingDetails,
                'fs_col' => $fs_col,
                'header_row' => $header_row,
                'unitNumbers' => json_encode($unitNumbers),
                'additional_property_ids' => json_encode($additional_property_ids)
            ], 200);
        }else{
            $amenities = json_decode($request->amenities);
            $categories = json_decode($request->categories);
            $filename = $request->filename;
            $property = json_decode($request->property);
            $amenityCategoryMapping = json_decode($request->amenityCategoryMapping);
            $mapped_array = json_decode($request->mapped_array);
            $fieldMappingDetails = $request->fieldMappingDetails;
            $unitNumbers = $request->unitNumbers;
            $fs_col = $request->fs_col;
            $header_row = (int)$request->header_row;
            $additional_property_ids = $request->additional_property_ids;
            return view('admin.property.map_category',compact('amenities','categories','filename','property','amenityCategoryMapping','mapped_array','fieldMappingDetails','unitNumbers','fs_col','header_row','additional_property_ids'));
        }

    }

    public function deleteFiles(Request $request)
    {
//        pe($request->all());
        $property_amenity_ids = explode(',',$request->amenity_files);
        $property_floor_ids = explode(',',$request->floor_files);

        if($request->permanent_delete == 1){
            Building::whereIn('property_id',$property_amenity_ids)->forceDelete();
            FloorPlan::whereIn('property_id',$property_floor_ids)->forceDelete();
        }else{
            $building_arr = Building::whereIn('property_id',$property_amenity_ids)->pluck('id');
            Building::destroy($building_arr);

            $floorPlanArr = FloorPlan::whereIn('property_id',$property_floor_ids)->pluck('id');
            UnitTypeDetail::whereIn('property_id',$property_floor_ids)->delete();
            FloorPlan::destroy($floorPlanArr);
        }
        Property::whereIn('id',$property_amenity_ids)->update(['completed' => 0]);
        Property::whereIn('id',$property_floor_ids)->update(['floor_file' => 0]);

        return redirect('admin/property')->with('success','Deleted Successfully');
    }

    public function deleteAmenityFile(Request $request,$id)
    {
        if($request->permanent_delete == 1){
            Building::where('property_id',$id)->forceDelete();
            Amenity::where('property_id',$id)->forceDelete();
            $query = File::where('property_id',$id)->where('file_type','p')->orWhere('file_type','pr');
            $files = $query->get();
            foreach($files as $fk => $fv){
                if($fv->file_type == 'p'){
                    $folder = 'app/files/property/';
                }else{
                    $folder = 'app/files/amenity-pricing-review/';
                }
                $del_path = storage_path($folder.$fv->filename);
                if(file_exists($del_path)) {
                    unlink($del_path);
                }
            }
            $query->forceDelete();
        }else{
            $building_arr = Building::where('property_id',$id)->pluck('id');
            Building::destroy($building_arr);
            $amenity_arr = Amenity::where('property_id',$id)->delete();
            Amenity::destroy($amenity_arr);
            File::where('property_id',$id)->where('file_type','p')->delete();
        }
        Property::where('id',$id)->update([
            'completed'         => 0,
            'pricing_review'    => 0
        ]);
        Notice::where('property_id',$id)->where('file_type','1')->delete();
        return response()->json([],200);
    }

    public function deleteSqftFile(Request $request,$id)
    {
        if($request->permanent_delete == 1){
            FloorPlan::where('property_id',$id)->forceDelete();
            UnitTypeDetail::where('property_id',$id)->forceDelete();
            $query = File::where('property_id',$id)->where('file_type','sq');
            $files = $query->get();
            foreach($files as $fk => $fv){
                $del_path = storage_path('app/files/floor-plan/'.$fv->filename);
                if(file_exists($del_path)) {
                    unlink($del_path);
                }
            }
            $query->forceDelete();
        }else{
            $floorPlanArr = FloorPlan::where('property_id',$id)->pluck('id');
            FloorPlan::destroy($floorPlanArr);
            $unitTypeDetailArr = UnitTypeDetail::where('property_id',$id)->pluck('id');
            UnitTypeDetail::destroy($unitTypeDetailArr);
            File::where('property_id',$id)->where('file_type','sq')->delete();
        }
        Property::where('id',$id)->update(['floor_file' => 0]);
        Notice::where('property_id',$id)->where('file_type','2')->delete();
        return response()->json([],200);
    }

    /**
     * Delete the APR details of the particular property and if it is permanent also deletes the file
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAPRFile(Request $request,$id)
    {
        if($request->permanent_delete == 1){
            AmenityPricingReview::where('property_id',$id)->forceDelete();
            $query = File::where('property_id',$id)->where('file_type','pr');
            $files = $query->get();
            foreach($files as $fk => $fv){
                $del_path = storage_path('app/files/amenity-pricing-review/'.$fv->filename);
                if(file_exists($del_path)) {
                    unlink($del_path);
                }
            }
            $query->forceDelete();
        }else{
            $APRarr = AmenityPricingReview::where('property_id',$id)->pluck('id');
            AmenityPricingReview::destroy($APRarr);
            File::where('property_id',$id)->where('file_type','pr')->delete();
        }
        Property::where('id',$id)->update(['pricing_review' => 0]);
        Notice::where('property_id',$id)->where('file_type','3')->delete();
        return response()->json([],200);
    }

    public function mapFSB(Request $request)
    {
        $filename = $request->filename;
        $property = Property::findOrFail($request->property_id);
        $amenities_arr = json_decode( $request->amenities_arr);
        $fieldMappingDetails = $request->fieldMappingDetails;
        $unitNumbers = json_decode($request->unitNumbers,true);
        $catMap = json_encode($request->cat_map);
        $fs_col = $request->fs_col;
        $header_row = $request->header_row;
        $additional_property_ids = $request->additional_property_ids;
        return view('admin.property.map_fsb',compact('filename','property','amenities_arr','fieldMappingDetails','unitNumbers','catMap','fs_col','header_row','additional_property_ids'));
    }
}
