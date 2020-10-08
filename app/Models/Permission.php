<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use SoftDeletes;

    protected $table = 'permissions';
    protected $guarded = ['id'];
    protected $dates = ['deleted_at'];

    /**
     * The roles that possess the permissions.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class,'permission_role');
    }
}
