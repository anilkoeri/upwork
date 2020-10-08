<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DOM extends Model
{
    protected $table = 'doms';

    protected $guarded = ['id'];
    protected $dates = ['created_at','updated_at'];
}
