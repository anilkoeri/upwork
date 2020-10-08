<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Building extends Model
{
    use SoftDeletes;

    protected $table = 'buildings';

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
     * Get the property of a building
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function property()
    {
        return $this->belongsTo('App\Models\Property');
    }

    /**
     * Return all the floors of the building
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function floors()
    {
        return $this->hasMany('App\Models\Floor');
    }

    /**
     * Return all the units through Floor of the building
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough]
     */
    public function units()
    {
        return $this->hasManyThrough('App\Models\Unit', 'App\Models\Floor');
    }

    public function amenityPricingReviews()
    {
        return $this->hasMany('App\Models\AmenityPricingReview');
    }


}
