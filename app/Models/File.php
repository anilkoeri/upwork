<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use SoftDeletes;
    protected $table = 'files';

    protected $guarded = ['id'];
    protected $dates = ['created_at','updated_at','deleted_at'];

    /**===========
     * Relations
    =============*/

    /**
     * Get the property of a file
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function property()
    {
        return $this->belongsTo('App\Models\Property');
    }
}
