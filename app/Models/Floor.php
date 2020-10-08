<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Floor extends Model
{
    use SoftDeletes;
    protected $table = 'floors';

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
     * Get the building of a floor
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function building()
    {
        return $this->belongsTo('App\Models\Building');
    }

    /**
     * Return all the floor groups
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public function floorGroup()
    {
        return $this->belongsTo('App\Models\FloorGroup');
    }

    /**
     * Return all the units of a floor
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function units()
    {
        return $this->hasMany('App\Models\Unit');
    }
}
