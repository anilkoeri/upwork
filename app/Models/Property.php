<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use \Staudenmeir\EloquentHasManyDeep\HasRelationships;
    protected $table = 'properties';

    protected $guarded = ['id'];
    protected $dates = ['created_at','updated_at','deleted_at','last_uploaded_at'];

    public function getCreatedAtAttribute($date)
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', $date)->format('Y-m-d H:i:s');
    }
    public function getUpdatedAtAttribute($date)
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', $date)->format('Y-m-d H:i:s');
    }
    public function getLastUploadedAtAttribute($date)
    {
        if($date){
            return Carbon::createFromFormat('Y-m-d H:i:s', $date)->format('Y-m-d H:i:s');
        }else{
            return '';
        }
    }

    /**
     * Return array of columns name of a table
     *
     * @return array
     */
    public function getTableColumns() {
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

    /**===========
     * Relations
    =============*/

    /**
     * Return all the buildings of a property
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function buildings()
    {
        return $this->hasMany('App\Models\Building');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function mappingTemplates()
    {
        return $this->hasMany('App\Models\MappingTemplate');
    }

    /**
     * Return the company of a property
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo('App\Models\Company');
    }

    public function units()
    {
        return $this->hasManyDeep('App\Models\Unit', ['App\Models\Building', 'App\Models\Floor']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function reviews()
    {
        return $this->hasMany('App\Models\Review');
    }

    /**
     * Return all the files of a property
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function files()
    {
        return $this->hasMany('App\Models\File');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function notices()
    {
        return $this->hasMany('App\Models\Notice');
    }

    public function floorPlans()
    {
        return $this->hasMany('App\Models\FloorPlan');
    }

}
