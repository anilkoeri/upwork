<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitAmenityValue extends Model
{
    use SoftDeletes;

    protected $table = 'units_amenities_values';

    protected $guarded = ['id'];
    protected $dates = ['created_at','updated_at','deleted_at'];

    /**===========
     * Relations
    =============*/

    /**
     * Get the amenity name
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function amenityValue()
    {
        return $this->belongsTo('App\Models\AmenityValue');
    }

    /**
     * Get the unit of the amenity values
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function unit()
    {
        return $this->belongsTo('App\Models\Unit');
    }


//    public static function boot()
//    {
//        parent::boot();
//        self::updating(function ($modal) {
//
//            if($modal->initial_amenity_value == $modal->amenity_value){
//                $status = 0;
//            }else{
//                $status = 2;
//            }
//            pe($modal);
//            $modal->status = $status;
//        });
//    }

}
