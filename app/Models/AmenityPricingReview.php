<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AmenityPricingReview extends Model
{
    use SoftDeletes;
    protected $table = 'amenity_pricing_reviews';

    protected $guarded = ['id'];
    protected $dates = [
        'application_date',
        'move_in_date',
        'lease_end_date',
        'notice_date',
        'move_out_date',
        'previous_notice_date',
        'previous_move_out_date',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    /**===========
     * Relations
    =============*/

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */

    public function property()
    {
        return $this->belongsTo('App\Models\Property');
    }
    public function building()
    {
        return $this->belongsTo('App\Models\Building');
    }
    public function unit()
    {
        return $this->belongsTo('App\Models\Unit');
    }
}
