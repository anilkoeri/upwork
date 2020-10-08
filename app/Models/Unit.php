<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use SoftDeletes;
    protected $table = 'units';

    protected $guarded = ['id'];
    protected $dates = ['created_at','updated_at'];

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
     * Get the floor of the unit
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function floor()
    {
        return $this->belongsTo('App\Models\Floor');
    }

    /**
     * Return all the amenities values of the unit
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function unitAmenityValues()
    {
        return $this->hasMany('App\Models\UnitAmenityValue');
    }

    /**
     * Return all the reviews of the unit
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function reviews()
    {
        return $this->hasMany('App\Models\Review');
    }

    /**
     * Return all the amenity pricing reviews of the unit
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function amenityPricingReviews()
    {
        return $this->hasMany('App\Models\AmenityPricingReview');
    }



}
