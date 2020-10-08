<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvitedUser extends Model
{
    const CREATED_AT = 'invited_at';

    protected $table = 'invited_users';

    protected $guarded = ['id'];
    protected $dates = ['invited_at'];

    public $timestamps = false;

    public function company()
    {
        return $this->belongsTo('App\Models\Company');
    }
}
