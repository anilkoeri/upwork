<?php

namespace App\Http\Controllers\Admin;

use App\Http\Services\AmenityService;
use App\Models\Building;
use App\Models\MappingTemplate;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\UploadedFile;
use League\Csv\Reader;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\AbstractHandler;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;

class AvailabilityController extends Controller
{
    protected $table,$service,$amenityPricingReview;
    public function __construct()
    {
//        $this->middleware('apr.subscription')->only(['create','index']);
        $this->service = new AmenityService();
//        $this->apr_helper = new InsertSampleAPR();

        $this->table = 'availabilities';
        \View::share('page_title', 'Availability Overlay');
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
        $max_file_upload_size = $this->service->getSettingBySlug('max-file-upload-size');
        return view('admin.availability.create',compact('max_file_upload_size','property'));

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
        $receiver = new FileReceiver("ao_file", $request, HandlerFactory::classFromRequest($request));

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
        $filePath = "files/availability/";
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
        $required_header_arr = ['unit_number', 'status'];

        /** Db header manually */
        $db_header = array(
            /** required starts */
            'unit_number'   => 'Unit Number',
            'status'        => 'Status',

            /** required ends */
            'building_number'           => 'Building Number',

        );

        $header_row = $request->header_row;
        $uploadType = $request->uploadType;
        if(empty($header_row) || $header_row < 1){
            $header_row = 0;
        }else{
            $header_row = $header_row - 1;
        }

        $property_id = $request->property_id;
        $property = Property::find($property_id);
        if($property->completed == 0 || $property->completed == 2){
            return redirect()->route('availability.create',$property->id)->with('error','The Amenity Review for the selected property has not yet completely uploaded. Please wait for the upload to finish before uploading a Pricing Review file.');
        }
        $buildings = Building::where('property_id',$property_id)->get();

        // $path = $request->file('import_file')->getRealPath();
        $path = storage_path('app/files/availability/').$request->filename;
        if (!ini_get("auto_detect_line_endings")) {
            ini_set("auto_detect_line_endings", TRUE);
        }
        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset($header_row);
        $csv_header = $csv->getHeader();

        if(count(array_filter($csv_header)) != count($csv_header)) {
            return redirect()->route('availability.create',$property->id)->with('error','There was an error with the uploaded file. Please check to make sure the top row has a header and no data is outside of the table range, then try again.');
        }

        $sample_data = $csv->fetchOne($header_row);
        $date_validation_columns = array();
        foreach($sample_data as $sk => $sv){
            if($this->service->checkIfContainsWord('date',$sk)){
                $date_validation_columns[] = $sk;
            }

        }
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

        $sample_data = array_values($sample_data);
        $filename = $request->filename;

        if (!ini_get("auto_detect_line_endings")) {
            ini_set('auto_detect_line_endings',FALSE);
        }

        return view('admin.availability.import_fields', compact( 'csv_header', 'db_header','required_header_arr','default_mapping','sample_data','filename','mapping_templates','mapping_template','property','header_row','uploadType'));

    }

}
