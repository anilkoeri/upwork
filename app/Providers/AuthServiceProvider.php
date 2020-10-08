<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Models\Amenity' => 'App\Policies\AmenityPolicy',
        'App\Models\Category' => 'App\Policies\CategoryPolicy',
        'App\Models\Company' => 'App\Policies\CompanyPolicy',
        'App\Models\MappingTemplate' => 'App\Policies\MappingTemplatePolicy',
        'App\Models\Notice' => 'App\Policies\NoticePolicy',
        'App\Models\Property' => 'App\Policies\PropertyPolicy',
        'App\Models\Role' => 'App\Policies\RolePolicy',
        'App\Models\Setting' => 'App\Policies\SettingPolicy',
        'App\Models\Unit' => 'App\Policies\UnitPolicy',
        'App\Models\User' => 'App\Policies\UserPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //


        Gate::define('scrubberView', 'App\Policies\ScrubberPolicy@scrubberView');
    }
}
