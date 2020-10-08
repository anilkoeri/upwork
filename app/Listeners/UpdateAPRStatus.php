<?php


namespace App\Listeners;


use App\Events\APRUploaded;

class UpdateAPRStatus
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  APRUploaded  $event
     * @return void
     */
    public function handle(APRUploaded $event)
    {
        //
    }
}
