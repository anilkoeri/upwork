<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;
    protected $table = 'categories';

    protected $guarded = ['id'];
    protected $dates = ['created_at','updated_at','deleted_at'];

    /**===========
     * Function
    =============*/
    public function categoryNameById($category_id)
    {
        $cat =  Category::findOrFail($category_id);
        return $cat->category_name;
    }


    /**===========
     * Relations
    =============*/


//    /**
//     * Returns all the related units
//     *
//     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
//     */
//    public function units()
//    {
//        return $this->belongsToMany('App\Models\Amenity','amenity_category');
//    }

    /**
     * Return all the units of a floor
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function amenities()
    {
        return $this->hasMany('App\Models\Amenity');
    }

//    /**
//     * Return all the units of a categories
//     *
//     * @return \Illuminate\Database\Eloquent\Relations\HasMany
//     */
//    public function childCategories()
//    {
//        return $this->hasMany('App\Models\Category','parent_id');
//    }
//    public function property()
//    {
//        return $this->belongsTo('App\Models\Property');
//    }

    public function company()
    {
        return $this->belongsTo('App\Models\Company');
    }

}
