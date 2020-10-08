<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NonUnit extends Model
{
    protected $table = 'non_units';

    protected $guarded = ['id'];
    protected $dates = ['created_at','updated_at'];

    /**===========
     * Relations
    =============*/

    /**
     * Get the building of a non-unit
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function building()
    {
        return $this->belongsTo('App\Models\Building');
    }

}
