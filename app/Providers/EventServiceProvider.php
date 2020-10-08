<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
//        Registered::class => [
//            SendEmailVerificationNotification::class,
//        ],
        'App\Events\AmenityUploaded' => [
            'App\Listeners\UpdateAmenityStatus',
        ],
        'App\Events\FloorPlanUploaded' => [
            'App\Listeners\UpdateFloorPlanStatus',
        ],
        'App\Events\APRUploaded' => [
            'App\Listeners\UpdateAPRStatus',
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
