<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\APRHelper;
use App\Helpers\InsertSampleAPR;
use App\Http\Services\AmenityService;
use App\Jobs\StoreAPR;
use App\Models\AmenityPricingReview;
use App\Models\Building;
use App\Models\Category;
use App\Models\Company;
use App\Models\File;
use App\Models\MappingTemplate;
use App\Models\Property;
use App\Rules\AdditionalFieldValidate;
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
use App\Models\SearchData;

use Auth,DB;

class AmenityPricingReviewController extends Controller
{
    protected $table,$apr_helper, $service,$amenityPricingReview;
    public function __construct()
    {
        $this->middleware('apr.subscription')->only(['create','index']);
        $this->service = new AmenityService();
        $this->apr_helper = new InsertSampleAPR();

        $this->table = 'amenity_pricing_reviews';
        \View::share('page_title', 'Amenity Pricing Reviews');
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index( Request $request)
    {

        $auth_user = \Auth::user();
        $search_property_id = ($request->property)? $request->property:0;
        $searchData = SearchData::where('user_id', $auth_user->id)->where('property_id', $search_property_id)->first();

        $buildingIds = array();
        $selectedPropertyId = ($searchData)? $searchData->property_id : $request->property;
        $selectedBuildingId = $selectedCompanyId = 0;

        //if building is defined
        //finds selectedBuildingId, selectedPropertyId and selectedCompanyId
//        if($request->building){
//            $selectedBuildingIds = $request->building;
//            $building = Building::with(['property','property.company'])->findOrFail($selectedBuildingIds);
//            $property = $building->property;
//            $selectedPropertyId = $property->id;
//            $company = $property->company;
//            $selectedCompanyId = $company->id;
//            if(!$auth_user->hasRole('superAdmin')){
//                if($auth_user->company_id != $selectedCompanyId )
//                {
//                    abort('403');
//                }
//            }
//        }

        //check if property is defined or property is referenced
        //finds selectedPropertyId and selectedCompanyId (selectedBuildingId needs to be find later)
        $property = Property::with(['company'])->withCount('units')->findOrFail($selectedPropertyId);
        $company = $property->company;
        $selectedCompanyId = $company->id;
        if(!$auth_user->hasRole('superAdmin')){
            if($auth_user->company_id != $selectedCompanyId )
            {
                abort('403');
            }
        }


        //check if company is defined or company is referenced
        //finds selectedCompanyId (selectedBuildingId and selectedPropertyId needs to be find later)
        if(empty($selectedCompanyId) && $request->company){
            $selectedCompanyId = $request->company;
            $company = Company::findOrFail($selectedPropertyId);
            if(!$auth_user->hasRole('superAdmin')){
                if($auth_user->company_id != $selectedCompanyId )
                {
                    abort('403');
                }
            }
        }

        //load Companies
        if($auth_user->hasRole('superAdmin')){
            $all_companies = Company::with(['properties'])->get(['id','name']);
        }else{
            $all_companies = Company::with(['properties'])->where('id',$auth_user->company_id)->get(['id','name']);
        }

        //remove companies which do not have property.
        $companies = $all_companies->reject(function($company){
            if($company->properties->count() == 0){
                return true;
            }
        });

        //load properties of selectedCompanyId
        $properties = Property::with('buildings')->where('company_id',$selectedCompanyId)->get();

        //find selectedPropertyId if empty
        if( empty($selectedPropertyId) && count($properties) > 0 ){
            $selectedPropertyId = $properties->first()->id;
            $property = Property::withCount('units')->findOrFail($selectedPropertyId);
        }

        //load buildings of selectedPropertyId
        $buildings = Building::where('property_id', $selectedPropertyId)->get();

        if($searchData){
            $buildingIds = json_decode($searchData->building_ids);
        }else{
            //find selectedBuildingId if empty
            if (empty($selectedBuildingId) && count($buildings) > 0) {
                $buildingIds = $buildings->pluck('id');
            }
        }
        $building = Building::findOrFail($buildingIds[0]);

        if(empty($buildingIds) ){
            abort(404);
        }

//        $is_dom_uploaded = AmenityPricingReview::where('building_id',$selectedBuildingId)->first();
//        if(!$is_dom_uploaded){
//            return view('admin.amenity_pricing_review.index',compact('companies','properties','buildings','company','property','building','cat_data_arr'));
//        }

        //load Categories
//        $statement = DB::select(DB::raw("SELECT IF(COUNT(u.id) > 1, GROUP_CONCAT(CONCAT(a.amenity_name, '_' ,a.id)), a.amenity_name) as m_concat, u.id as unit_id, u.building_id, (uav.id) as uav_id, a.* FROM amenities a INNER JOIN amenity_values av ON a.id = av.amenity_id INNER JOIN units_amenities_values uav ON av.id = uav.amenity_value_id INNER JOIN units u ON u.id = uav.unit_id where a.category_id = '370' AND a.property_id = '82' AND u.building_id = '1265' group by u.id order by u.id asc"));
//        pe($statement);

      $results = DB::table('units as u')
          ->leftJoin('units_amenities_values as uav','u.id','=','uav.unit_id')
          ->leftJoin('amenity_values as av','uav.amenity_value_id','=','av.id')
          ->leftJoin('amenities as a','av.amenity_id','=','a.id')
          ->leftJoin('categories as c','a.category_id','=','c.id')
          ->leftJoin('amenity_pricing_reviews as apr','apr.unit_id','=','u.id')
          ->select('u.id as unit_id','u.unit_number','a.id as amenity_id','a.amenity_name','a.category_id','c.category_name','av.amenity_value','uav.id as uav_id','apr.dom')
          ->whereIn('u.building_id',$buildingIds)
          ->whereNull('a.deleted_at')
          ->whereNull('av.deleted_at')
          ->whereNull('c.deleted_at')
          ->whereNull('uav.deleted_at')
            ->whereNull('apr.deleted_at')
          ->orderBy('c.category_name')
          ->orderBy('a.amenity_name','asc')
          ->get()
          ->groupBy(['category_id','unit_id']);

//        pe($results);

        $cat_data_arr = array();
//        pe($results);
        foreach($results as $rk => $rv){
            $am_data_arr1 = $am_data_arr2 = array();
            $cat_count = 0;
            $obs = 0;
            foreach($rv as $rvk => $rvv){
                $am_count_unit = count($rvv);
                if($am_count_unit > 1){
                    //if one unit has more than one amenity from same category
                    $concat_amen_id = $concat_amen_name = '';
                    foreach($rvv as $k => $v){
                        if($k != 0){
                            $concat_amen_id .= '_';
                            $concat_amen_name .= ' & ';
                        }else{
                            $am_val = 0;
                        }
                        $concat_amen_id .= $v->amenity_id;
                        $concat_amen_name .= $v->amenity_name;
                        $am_val = $am_val + $v->amenity_value;
                        $obs = ($v->dom != '') ? 1 : 0;
                    }
                    if(!isset($am_data_arr2[$concat_amen_id])){
                        $arr = array();
                        $arr = [
                            'amenity_id'   => $concat_amen_id,
                            'amenity_name' => $concat_amen_name
                        ];
                        $am_data_arr2[$concat_amen_id]['details'] = $arr;
                        $am_data_arr2[$concat_amen_id]['sum'] = $am_val;
                        $am_data_arr2[$concat_amen_id]['obs'] = 0;
                    }

                    if(!isset($am_data_arr2[$concat_amen_id]['count'])){
                        $am_data_arr2[$concat_amen_id]['count'] = 0;
                    }
//                    $am_data_arr2[$concat_amen_id]['sum'] = $am_data_arr2[$concat_amen_id]['sum'] + $am_val;
                    $am_data_arr2[$concat_amen_id]['count'] = $am_data_arr2[$concat_amen_id]['count'] + 1;
                    $cat_count = $cat_count + 1;

                    $am_data_arr2[$concat_amen_id]['obs'] += $obs;
                }else{
                    foreach($rvv as $k => $v){
                        if(!isset($am_data_arr1[$v->amenity_id])){
                            $arr = array();
                            $arr = [
                               'amenity_id'   => $v->amenity_id,
                               'amenity_name' => $v->amenity_name
                            ];
                            $am_data_arr1[$v->amenity_id]['details'] = $arr;
                            $am_data_arr1[$v->amenity_id]['sum'] = $v->amenity_value;
                            $am_data_arr1[$v->amenity_id]['obs'] = 0;
                        }
                        if(!isset($am_data_arr1[$v->amenity_id]['count'])){
                            $am_data_arr1[$v->amenity_id]['count'] = 0;
                        }
                        $am_data_arr1[$v->amenity_id]['count'] = $am_data_arr1[$v->amenity_id]['count'] + 1;
                        $cat_count = $cat_count + 1;
                        $obs = ($v->dom != '') ? 1 : 0;
                        $am_data_arr1[$v->amenity_id]['obs'] += $obs;
                    }
                }
            }
            $cat_data_arr[$rk]['amenities_list'] = $am_data_arr1 + $am_data_arr2;
            $cat_data_arr[$rk]['category_name']  = $v->category_name;
            $cat_data_arr[$rk]['category_count']  = $cat_count;
        }

        //Units count
        $total_units_count = $property->units_count;
        //        pe($cat_data_arr);

        //        $category_id = array_key_first($cat_data_arr);
        //
        //        $final_data = APRHelper::getAprTableDetails($category_id,$selectedPropertyId,$selectedBuildingId);

        if (!$searchData)
            $searchData = SearchData::initialize();

    return view('admin.amenity_pricing_review.index',compact('companies','properties','buildings','company','property','building','cat_data_arr','total_units_count', 'searchData'));

    }

    /**
     * Show the file upload form for uploading a APR csv file.
     *
     * @param $property_id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function create($property_id)
    {
        $property = Property::find($property_id);
        $this->authorize('create', Property::class);
        $max_file_upload_size = $this->service->getSettingBySlug('max-file-upload-size');
        return view('admin.amenity_pricing_review.create',compact('max_file_upload_size','property'));

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
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
        $receiver = new FileReceiver("apr_file", $request, HandlerFactory::classFromRequest($request));

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
        $filePath = "files/amenity-pricing-review/";
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
        $required_header_arr = ['unit_number', 'resident_id', 'dom'];


        /** Db header manually */
        $db_header = array(
            /** required starts */
            'unit_number'   => 'Unit Number',
            'resident_id'   => 'Resident ID',
            'dom'           => 'Days On Market',

            /** required ends */
            'building_number'           => 'Building Number',
            'community_name'            => 'Community Name',
            'application_date'          => 'Application Date',
            'move_in_date'              => 'Move In Date',
            'lease_end_date'            => 'Lease End Date',
            'notice_date'               => 'Notice Date',
            'move_out_date'             => 'Move Out Date',
            'previous_notice_date'     => 'Previous Notice Date',
            'previous_move_out_date'    => 'Previous Move Out Date',

        );

        $header_row = $request->header_row;
        if(empty($header_row) || $header_row < 1){
            $header_row = 0;
        }else{
            $header_row = $header_row - 1;
        }

        $property_id = $request->property_id;
        $property = Property::find($property_id);
        if($property->completed == 0 || $property->completed == 2){
            return redirect('admin/amenity_pricing_review/create/property/'.$property_id)->with('error','The Amenity Review for the selected property has not yet completely uploaded. Please wait for the upload to finish before uploading a Pricing Review file.');
        }
        $buildings = Building::where('property_id',$property_id)->get();


        // $path = $request->file('import_file')->getRealPath();
        $path = storage_path('app/files/amenity-pricing-review/').$request->filename;
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
            return redirect('admin/amenity_pricing_review/create/property/'.$property_id)->with('error','There was an error with the uploaded file. Please check to make sure the top row has a header and no data is outside of the table range, then try again.');
        }

        $sample_data = $csv->fetchOne($header_row);
        $date_validation_columns = array();
        foreach($sample_data as $sk => $sv){
            if($this->service->checkIfContainsWord('date',$sk)){
                $date_validation_columns[] = $sk;
            }

        }

//        $date_validation = true;
//        foreach($date_validation_columns as $col){
//            $date_validation = $this->service->validateDate($sample_data[$col]);
//            if($date_validation != true){
//                return redirect('admin/amenity_pricing_review/create/property/'.$property_id)->with('error','One Or More Column has invalid date. Please, format it first to yyyy-mm-dd format');
//                break;
//            }
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

        return view('admin.amenity_pricing_review.import_fields', compact( 'csv_header', 'db_header','required_header_arr','default_mapping','sample_data','filename','mapping_templates','mapping_template','property','header_row'));

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        if($request->ajax()){

            $property = Property::findOrFail($request->property_id);

            $required_all = array('unit_number', 'resident_id', 'dom');
            $rules = [
                'row' => [new RequiredCSVColumn($request->additional_field,$required_all)],
                'additional_field' => [new AdditionalFieldValidate()],
                'mapping_template_name' => ['required_with:saveMapping',new UniqueTemplatePerCompany($property->company_id)],
            ];

            $customMessages = [
                'mapping_template_name.required_with' => 'The Mapping Template Name cannot be blank (if you want to save it).',
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

            $csv_file_path = storage_path('app/files/amenity-pricing-review/').$request->filename;
            $csv = Reader::createFromPath($csv_file_path, 'r');
            $input_bom = $csv->getInputBOM();
            if ($input_bom === Reader::BOM_UTF16_LE || $input_bom === Reader::BOM_UTF16_BE) {
                $csv->addStreamFilter('convert.iconv.UTF-16/UTF-8');
            }
            $csv->skipEmptyRecords();
            $csv->setHeaderOffset($header_row);

            $building_numbers = array();
            $building_number_key = array_search('building_number', $request->row);
            if($building_number_key){
                $building_number_results = $csv->fetchColumn($building_number_key);
                $building_number_lists = iterator_to_array($building_number_results, false);
                if($header_row > 0){
                    $building_number_lists = array_slice($building_number_lists,$header_row);
                }
                $building_numbers = array_filter(array_unique($building_number_lists));
                $building_numbers = array_map('trim',$building_numbers);
            }else{
                //default building_numbers if no building numbers are provided
                $buildings_first = Building::where('property_id',$property->id)
//                    ->get()
//                    ->sortBy('building_number')
                    ->orderBy('building_number','asc')
                    ->first();
                $building_numbers[] = $buildings_first->building_number;
            }

            $buildings = Building::where('property_id',$property->id)->pluck('building_number','id')->toArray();
            $buildings_ids_arr = array();

            foreach($buildings as $bk => $bv){
                if(!in_array(trim($bv), $building_numbers)){
                    unset($buildings[$bk]);
                }
            }

            $arr_intersect = array_intersect( array_map('strtolower', $buildings), array_map('strtolower', $building_numbers) );

            if( count($arr_intersect) < count($building_numbers) ){
                $err_msg = "Some buildings from uploaded Pricing Review File could not be found in Amenity Audit. Please correct the building numbers in Pricing Review Files and upload it again.
                            <br/>
                            Buildings in Amenity Audit: [".implode(",",$buildings)."]
                            </br>
                            Buildings in Amenity Pricing Review: [".implode(",",$building_numbers)."]";
                return response()->json([
                    'errors'    => [
                        'building_number'   => [
                            $err_msg
//                            'Some unit numbers from Amenity Pricing Review files could not be found in Amenity Audit upload file. Please check units: '
                        ]
                    ]
                ],422);
            }


            $data = [
                'user'              => $auth_user,
                'url'               => url()->current(),
                'row_value'         => $header_row+2,
                'offset'            => $header_row,
                'limit'             => $limit,
                'file_name'         => $request->filename,
                'map_data'          => $row,
                'additional_value'  => $additional_value,
                'property_id'       => $request->property_id,
                'header_row'        => $header_row,
                'building_details'  => $buildings
            ];

            $error_arr = array();
            $error_row_numbers = array();

            $resp = $this->apr_helper->insertSampleAmenityPricingReviewData($data,$error_arr,$error_row_numbers);
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
                'file_type' => 'pr',
                'property_id' => $property->id
            ]);

//            Property::where('id', $data['property_id'])->update(['pricing_review' => 2]);

            $pricingReviewDispatch = (new StoreAPR($data,$error_arr,$error_row_numbers))->delay(Carbon::now()->addSeconds(3));
            dispatch($pricingReviewDispatch);

            $property->last_uploaded_at = date('Y-m-d H:i:s');
            $property->pricing_review = 2;
            $property->save();

            $msg = [
                'status' => '1'
            ];
            echo json_encode($msg);
            exit();

        } //request ajax
    }

    /**
     * @param Request $request
     * @param $property_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAPRDetails(Request $request, $property_id)
    {
        $request->validate([
            'category_id'   => 'required',
            // 'building_ids'  => 'required',
            // 'base_id'       => 'required',
            // 'checked_ids'   => 'required'
        ]);
        $aprDataDb = $aprData = [
            'category_id'   => $request->category_id,
            'property_id'   => $property_id,
            'building_ids'  => $request->building_ids,
            'base_id'       => $request->base_id,
            'checked_ids'   => $request->checked_ids
        ];

        $auth_user = Auth::user();
        $searchData = SearchData::where('user_id', $auth_user->id)->where('property_id', $property_id)->first();
        if($searchData){
            $aprDataDb['user_id'] = $auth_user->id;
            $aprDataDb['building_ids'] = json_encode($aprDataDb['building_ids']);
            $aprDataDb['checked_ids'] = json_encode($aprDataDb['checked_ids']);
            $searchData->update($aprDataDb);
        }else{
            $aprDataDb['user_id'] = $auth_user->id;
            $aprDataDb['building_ids'] = json_encode($aprDataDb['building_ids']);
            $aprDataDb['checked_ids'] = json_encode($aprDataDb['checked_ids']);
            SearchData::create($aprDataDb);
        }

        $resp = APRHelper::getAprTableDetails($aprData);
        $aprTableData = \View::make('admin.amenity_pricing_review.__aprAjaxTable')
            ->with('final_data', $resp['final_data'])
            ->with('category_units_count', $resp['category_units_count'])
            ->with('total_units_count', $request->total_units_count)
            ->with('base_id', $request->base_id)
//            ->with('none_data', $resp['none_data'])
//            ->with('none_category_units_count', $resp['none_category_units_count'])
            ->render();

        return response()->json([
            'aprTableData' => $aprTableData
        ],200);

    }

    public function aprSubscriptionError($property_id)
    {
        $property = Property::find($property_id);
        return view('admin.amenity_pricing_review.aprSubscriptionError',compact('property'));
    }

}
