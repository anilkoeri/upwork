<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AmenityValue extends Model
{
    use SoftDeletes;
    protected $table = 'amenity_values';

    protected $guarded = ['id'];
    protected $dates = ['created_at','updated_at','deleted_at'];

    /**==========
     * Relations
     =============*/
    /**
     * Returns the related amenity
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function amenity()
    {
        return $this->belongsTo('App\Models\Amenity');
    }

        /**
     * Get the list of units which possess this amenity values
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function unitAmenityValues()
    {
        return $this->hasMany('App\Models\UnitAmenityValue');
    }

    /**
     * Get the list of reviews related to the amenity
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function reviews()
    {
        return $this->hasMany('App\Models\Review');
    }
}
