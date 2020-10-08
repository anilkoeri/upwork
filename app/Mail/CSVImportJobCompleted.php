<?php

namespace App\Mail;

use App\Http\Services\AmenityService;
use App\Models\Notice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class CSVImportJobCompleted extends Mailable
{
    use Queueable, SerializesModels;

    public $data_arr;

    /**
     * Create a new message instance.
     *
     * @param array $data_arr
     * @return void
     */
    public function __construct($data_arr)
    {

        $this->data_arr = $data_arr;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $service = new AmenityService();

        $address = $service->getSettingBySlug('amenity-outgoing-mail-address');
        $subject = 'File Uploaded Successfully';
        $name = $service->getSettingBySlug('site-title');

        $headerData = [
            'category' => 'category',
            'unique_args' => [
                'variable_1' => 'abc'
            ]
        ];

        $header = $this->asString($headerData);

        $this->withSwiftMessage(function ($message) use ($header) {
            $message->getHeaders()
                ->addTextHeader('X-SMTPAPI', $header);
        });


        return $this->view('emails.csv_import_job_complete')
            ->from($address, $name)
            ->cc($address, $name)
            ->bcc($address, $name)
            ->replyTo($address, $name)
            ->subject($subject)
            ->with(['data' => $this->data_arr['mailBody'] ]);

    }

    private function asJSON($data)
    {
        $json = json_encode($data);
        $json = preg_replace('/(["\]}])([,:])(["\[{])/', '$1$2 $3', $json);

        return $json;
    }


    private function asString($data)
    {
        $json = $this->asJSON($data);

        return wordwrap($json, 76, "\n   ");
    }
}
