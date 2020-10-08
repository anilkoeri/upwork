<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FloorGroup extends Model
{
    use SoftDeletes;
    protected $table = 'floor_groups';

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
    public function floors()
    {
        return $this->hasMany('App\Models\Floor');
    }

}
