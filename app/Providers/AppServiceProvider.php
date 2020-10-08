<?php

namespace App\Providers;

use App\Models\Amenity;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Unit;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
        setlocale(LC_MONETARY, 'en_US');

        /**Property delete cascade **/
        Unit::deleting(function($unit){
            $unit->unitAmenityValues()->delete();
        });
        Floor::deleting(function($floor) {
            $floor->units->each(function($unit){
                $unit->delete();
            });
        });
        Building::deleting(function($building) {
            $building->property->reviews()->delete();
            $building->floors->each(function($floor){
               $floor->delete();
            });
            $building->amenityPricingReviews()->delete();
        });
        Amenity::deleting(function($amenity){
            $amenity->amenityValues()->delete();
        });



        Building::restored(function($building) {
            $building->floors()->withTrashed()->restore();
            $building->floors()->units()->withTrashed()->restore();
            $building->floors()->units()->unitAmenityValues()->withTrashed()->restore();
            $building->property->reviews()->withTrashed()->restore();
            $building->amenityPricingReviews()->withTrashed()->restore();
        });
        Amenity::restored(function($amenity) {
            $amenity->amenityValues()->withTrashed()->restore();
        });


    }
}
