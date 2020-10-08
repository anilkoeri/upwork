<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchData extends Model
{
    protected $table = 'search_data';

    protected $guarded = ['id'];
    protected $dates = ['created_at','updated_at'];

    public static function initialize()
    {
        return (object) [
            'user_id' => '', 'property_id' => '', 'category_id' => '', 'base_id' => 0, 'building_ids' => '[]', 'checked_ids' => '[]'
        ];
    }
}
