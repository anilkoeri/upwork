<?php

namespace App\Listeners;

use App\Events\AmenityUploaded;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateAmenityStatus
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
     * @param  AmenityUploaded  $event
     * @return void
     */
    public function handle(AmenityUploaded $event)
    {
//        $complete_status = $event->property->completed;
    }
}
