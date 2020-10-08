<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitTypeDetail extends Model
{
    use SoftDeletes;
    protected $table = 'unit_type_details';

    protected $guarded = ['id'];
    protected $dates = ['created_at','updated_at'];

    /**===========
     * Relations
    =============*/

    /**
 * Get the floor
 *
 * @return \Illuminate\Database\Eloquent\Relations\HasMany
 */
    public function floorPlans()
    {
        return $this->hasMany('App\Models\FloorPlan','unit_type_id','id');
    }

    public function floorPlan()
    {
        return $this->belongsTo('App\Models\FloorPlan','base_floor_plan_id','id');
    }
}
