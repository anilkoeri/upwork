<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Amenity extends Model
{
    use SoftDeletes;
    protected $table = 'amenities';

    protected $guarded = ['id'];
    protected $dates = ['created_at','updated_at','deleted_at'];

    /**
     * Return array of columns name of a table
     *
     * @return array
     */
    public function getTableColumns() {
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

    /**===========
     * Relations
    =============*/

    /**
     * Get the list of amenity values of different units of a particular amenity type
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function amenityValues()
    {
        return $this->hasMany('App\Models\AmenityValue');
    }

//    /**
//     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
//     */
//    public function unitAmenityValues()
//    {
//        return $this->hasManyThrough('App\Models\UnitAmenityValue', 'App\Models\AmenityValue');
//    }

//    /**
//     * Get the list of amenity values of different units of a particular amenity type
//     *
//     * @return \Illuminate\Database\Eloquent\Relations\HasMany
//     */
//    public function unitAmenityValues()
//    {
//        return $this->hasMany('App\Models\UnitAmenityValue');
//    }


    /**
     * Returns all the related categories
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo('App\Models\Category');
    }

    /**
     * Returns the related amenityLevel
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function amenityLevel()
    {
        return $this->belongsTo('App\Models\AmenityLevel');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function property()
    {
        return $this->belongsTo('App\Models\Property');
    }




}
