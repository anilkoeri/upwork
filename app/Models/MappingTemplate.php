<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MappingTemplate extends Model
{
    protected $table = 'mapping_templates';
    protected $guarded = ['id'];
    protected $dates = ['created_at','updated_at'];

    /**===========
     * Relations
    =============*/

    /**
     * Get the Owner name
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User','created_by','id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function property()
    {
        return $this->belongsTo('App\Models\Property');
    }

}
