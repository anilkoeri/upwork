<?php

namespace App\Jobs;

use App\Mail\CSVFailedJob;
use App\Mail\ExportedSuccessfully;
use App\Mail\ExportFailed;
use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Rap2hpoutre\FastExcel\FastExcel;

use Mail;

class ExportReview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data,$error_arr,$error_row_numbers,$table;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data,$error_arr,$error_row_numbers) {
        $this->data = $data;
        $this->error_arr = $error_arr;
        $this->error_row_numbers = $error_row_numbers;
        $this->table = 'reviews';
    }

    /**
     * @throws \Box\Spout\Common\Exception\IOException
     * @throws \Box\Spout\Common\Exception\InvalidArgumentException
     * @throws \Box\Spout\Common\Exception\UnsupportedTypeException
     * @throws \Box\Spout\Writer\Exception\WriterNotOpenedException
     */
    public function handle()
    {
        $reviews = Review::with(['unit','amenityValue','amenityValue.amenity','amenityValue.amenity.category','amenityValue.unitAmenityValues'])
            ->where('property_id',$this->data['property_id'])
            ->orderBy('created_at','desc')
            ->get();

        $file_name = 'review-' . time() . '.xlsx';
        $file_path = storage_path('app/public/exports/'.$file_name);
//        $file_path = public_path('/export/'.$file_name);

        (new FastExcel($reviews))->export($file_path, function ($review) {
            if($review->status != 3) {
                if ($review->action == 2) {
                    $count_num = (int)count($review->amenityValue->unitAmenityValues);
                    $unit_num = 'Multiple';
                } elseif ($review->action == 5) {
                    $count_num = (int)0;
                    $unit_num = '';
                } else {
                    $count_num = (int)1;
                    $unit_num = (int)$review->unit->unit_number;
                }

                if ($review->status == 1) {
                    $action = 'Pending';
                }else {
                    $action = 'Accepted';
                }

                return [
                    'Total Units' => $count_num,
                    'Unit Number' => $unit_num,
                    'Category Name' => ($review->amenityValue) ? $review->amenityValue->amenity->category->category_name : '',
                    'Amenity Name' => ($review->amenityValue) ? $review->amenityValue->amenity->amenity_name : '',
                    'New Amenity Value' => $review->new_amenity_value,
                    'Old Amenity Value' => $review->old_amenity_value,
                    'Action' => $action
                ];

            }
        });

        $arr_data = [
            'file_name' => $file_name,
            'user_name' => $this->data['user']['name'],
            'user_id' => $this->data['user']['id'],
            'base_url' => $this->data['base_url']
        ];


        Mail::to($this->data['user']['email'])->send(new ExportedSuccessfully($arr_data));

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
            'user_id' => $this->data['user']['id'],
            'user_name' => $this->data['user']['name'],
            'error_message' => $message,
        ];
        Mail::to($this->data['user']['email'])->send(new ExportFailed($arr_data));

    }
}
