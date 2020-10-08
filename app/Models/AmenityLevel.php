<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmenityLevel extends Model
{
    protected $table = 'amenity_levels';

    protected $guarded = ['id'];
    protected $dates = ['created_at','updated_at'];

    /**===========
     * Relations
    =============*/

    /**
     * Return all amenities that falls into this level
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function amenities()
    {
        return $this->hasMany('App\Models\Amenity');
    }
}
