<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $table = 'email_templates';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $guarded = ['id'];
}
