<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AmenityCategoryMapping extends Model
{
    use SoftDeletes;
    protected $table = 'amenity_category_mapping';

    protected $guarded = ['id'];
    public $timestamps = false;
    protected $dates = ['created_at','updated_at','deleted_at'];

    /** Relations */

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo('App\Models\Category');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo('App\Models\Company');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function property()
    {
        return $this->belongsTo('App\Models\Property');
    }
}
