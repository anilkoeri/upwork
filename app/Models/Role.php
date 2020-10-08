<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';
    protected $guarded = ['id'];
    protected $dates = ['created_at','updated_at'];

    /*Relations*/
    public function permissions()
    {
        return $this->belongsToMany(Permission::class,'permission_role');
    }
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Functions
     */

    public function inRole($permission)
    {
        if (is_string($permission)) {
            return $this->permissions->contains('name', $permission);
        }
//        return !! $permission->intersect($this->permissions)->count();
    }

    /**
     * Check whether the role contains the provided permission or not
     *
     * @param int $permission
     * @return boolean
     *
     */
    public function containsPermission($permission)
    {
        return $this->permissions->contains($permission);
    }
}
