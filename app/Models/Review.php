<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use SoftDeletes;
    protected $table = 'reviews';

    protected $guarded = ['id'];
    protected $dates = ['created_at','updated_at'];

    /**===========
     * Relations
    =============*/

    /**
     * Return respected unit
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function unit()
    {
        return $this->belongsTo('App\Models\Unit');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function property()
    {
        return $this->belongsTo('App\Models\Property');
    }

    /**
     * Return respected amenity
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function amenityValue()
    {
        return $this->belongsTo('App\Models\AmenityValue');
    }


//    public function unitAmenityValues()
//    {
//        return $this->hasManyThrough(
//            'App\Models\UnitAmenityValue', //post
//            'App\Models\AmenityValue', //user
//            'id',
//            'amenity_value_id',
//            'amenity_value_id',
//            'id'
//
//        );
//    }

    public function unitAmenityValues()
    {
        return $this->hasMany(
            'App\Models\UnitAmenityValue',
            'amenity_value_id',
            'amenity_value_id'
        );
    }



}
