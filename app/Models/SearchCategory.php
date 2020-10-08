<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchCategory extends Model
{
    protected $table = 'search_category';

    protected $guarded = ['id'];
    protected $dates = ['created_at','updated_at'];

    public static function initialize()
    {
        return (object) [
            'user_id' => '', 'building_ids' => '[]', 'cat_ids' => '[]', 'amenities_list' => '[]'
        ];
    }
}
