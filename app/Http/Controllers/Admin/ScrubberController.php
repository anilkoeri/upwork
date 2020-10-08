<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ExportFormattedCSV;
use App\Http\Services\AmenityService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\UploadedFile;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\AbstractHandler;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;

use Maatwebsite\Excel\Concerns\ShouldAutoSize;

use Maatwebsite\Excel\Concerns\WithHeadings;

use Excel;

class ScrubberController extends Controller implements ShouldAutoSize
{
    private $table,$service;
    public function __construct()
    {
        $this->service = new AmenityService();
        $this->table = 'properties';
        \View::share('page_title', 'Scrubber');
    }
    /**
     *
     * @param $property_id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index()
    {
        $this->authorize('scrubberView');
        $max_file_upload_size = $this->service->getSettingBySlug('max-file-upload-size');
        return view('admin.scrubber.create',compact('max_file_upload_size'));
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

        $filename = date('Y_m_d_his').'_'.$file->getClientOriginalName();
        // Build the file path
        $filePath = "files/scrubber/";
        $finalPath = storage_path("app/".$filePath);

        // move the file name
        $file->move($finalPath, $filename);

        return response()->json([
            'success' => true,
            'filename'=>$filename
        ]);
    }

    public function formatCSV(Request $request)
    {
        $data = [
            'file_name' => $request->filename,
            'header_row' => $request->header_row,
        ];
        return Excel::download(new ExportFormattedCSV($data), $request->filename);
    }
}
