<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Str;

class Notice extends Model
{
    protected $table = 'notices';

    protected $guarded = ['id'];
    protected $dates = ['created_at','updated_at'];

    /**===========
     * Relations
    =============*/

    /**
     * Get the User of a notification
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    /**     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function property()
    {
        return $this->belongsTo('App\Models\Property');
    }

    public function getUpdatedAtAttribute($date)
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', $date)->format('Y-m-d H:i:s');
    }

    /**
     * @return string
     */
    public static function generateRandomSlug()
    {
        do
        {
            $slug = Str::random('8');
            $notice = Notice::where('slug',$slug)->first();
        }
        while($notice);
        return $slug;
    }
}
