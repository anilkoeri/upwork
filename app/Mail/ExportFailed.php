<?php

namespace App\Mail;

use App\Http\Services\AmenityService;
use App\Models\Notice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ExportFailed extends Mailable
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
        $data = $service->getMessageBySlug('export-failed');
        $data->body = str_replace('[[USER_NAME]]',$this->data_arr['user_name'],$data->body);

        $array_from_to = array (
            '[[USER_NAME]]' => $this->data_arr['user_name'],
            '[[FILENAME]]' => $this->data_arr['filename'],
            '[[ERROR]]' => $this->data_arr['error_message'],
        );

        $data->body = str_replace(array_keys($array_from_to), $array_from_to, $data->body);


        $address = $service->getSettingBySlug('amenity-outgoing-mail-address');
        $subject = $data->title;
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

        Notice::create([
            'title' => $this->data_arr['filename'].' - Export Job Failed',
            'body' => json_encode($data->body),
            'user_id' => $this->data_arr['user_id']
        ]);


        return $this->view('emails.csv_failed_job')
            ->from($address, $name)
            ->cc($address, $name)
            ->bcc($address, $name)
            ->replyTo($address, $name)
            ->subject($subject)
            ->with([ 'data' => $data ]);

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
