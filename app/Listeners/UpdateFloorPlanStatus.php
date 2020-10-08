<?php

namespace App\Listeners;

use App\Events\FloorPlanUploaded;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateFloorPlanStatus
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
     * @param  FloorPlanUploaded  $event
     * @return void
     */
    public function handle(FloorPlanUploaded $event)
    {
        //
    }
}
