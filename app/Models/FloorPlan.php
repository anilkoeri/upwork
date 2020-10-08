<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FloorPlan extends Model
{
    use SoftDeletes;
    protected $table = 'floor_plans';

    protected $guarded = ['id'];
    protected $dates = ['created_at','updated_at'];

    /**===========
     * Relations
    =============*/

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function unitTypeDetail()
    {
        return $this->belongsTo('App\Models\UnitTypeDetail','unit_type_id','id');
    }

    public function property()
    {
        return $this->belongsTo('App\Models\Property');
    }
}
