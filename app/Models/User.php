<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable,SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime:d/m/Y', // Change your format
        'updated_at' => 'datetime:d/m/Y',
    ];

    /**
     * Relations
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }
    public function assignRole(Role $role)
    {
        return $this->roles()->save($role);
    }

    public function hasRole($role)
    {
        if (is_string($role)) {
            if($this->role->name == 'superAdmin'){
                return true;
            }
            return $this->role->name == $role;
        }
        $user_role = array();
        $user_role = [
            $this->role
        ];
        return !! $role->intersect($user_role)->count();

    }

    public function havePermission($permission)
    {
        $role = Auth::user()->role;
        if($role->name == 'superAdmin')
        {
            return true;
        }
        return $role->inRole($permission);
    }

    /**
     * Return all the notices of an user
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function notices()
    {
        return $this->hasMany('App\Models\Notice');
    }

    /**
     * Return all the mapping templates created by an user
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function mappingTemplates()
    {
        return $this->hasMany('App\Models\MappingTemplate','created_by','id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo('App\Models\Company');
    }

    /** overwriting emailVerification */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailNotification());
    }
}
