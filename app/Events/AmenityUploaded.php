<?php

namespace App\Events;

use App\Models\Notice;
use App\Models\Property;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AmenityUploaded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $property_ids = array();

    /**
     * Create a new event instance.
     *
     * AmenityUploaded constructor.
     * @param $property_ids
     */
    public function __construct(array $property_ids)
    {
        $this->property_ids = $property_ids;
    }

    /**
 * Get the channels the event should broadcast on.
 *
 * @return \Illuminate\Broadcasting\Channel|array
 */
    public function broadcastOn()
    {
//        return new PrivateChannel('channel.amenity.'.$this->property_id);
        return ['channel.amenity'];
    }


    public function broadcastAs()
    {
        return 'amenity-uploaded';
    }

    public function broadcastWith()
    {
        $res = array();
        // This must always be an array. Since it will be parsed with json_encode()
        foreach($this->property_ids as $pk => $pv){
            $prop = Property::find($pv);
            $trash = "";
            $warning = "";
            if($prop->completed == 1){
                $main = "<a href='".url('/admin/floor-stack/?company='.$prop->company_id.'&property='.$pv)."' class='btn btn-xs btn-primary d-inline-block mr-1'>View</a>";
                $trash = "<a href='#' class='btn btn-xs bg-light-grey delete_amenity mr-1'><i class='fa fa-trash text-white' aria-hidden='true'></i></a>";
            }elseif($prop->completed == 2){
                $main = "<a href='#' class='btn btn-xs btn-warning d-inline-block mr-1'>Pending</a>";
            }elseif($prop->completed == 3){
                $notice = Notice::where('property_id',$pv)->where('file_type','1')->first();
                $main = "<a href='".url('/admin/floor-stack/?company='.$prop->company_id.'&property='.$pv)."' class='btn btn-xs btn-primary d-inline-block mr-1'>View</a>";
                $trash = "<a href='#' class='btn btn-xs bg-light-grey delete_amenity'><i class='fa fa-trash text-white' aria-hidden='true'></i></a>";
                $warning = "<a class='d-inline-block' href='".url('/admin/notice/'.$notice->slug)."' data-toggle='tooltip' data-html='true' title='The upload has some potential errors. Please click here to check which errors were detected.'><i class='fa fa-exc fa-exclamation-circle text-warning align-middle' aria-hidden='true'></i></a>";
            }else{
                $main = "<a href='".url('/admin/property/create/'.$pv)."' class='btn btn-xs btn-secondary d-inline-block mr-1'><i class='fa fa-arrow-circle-up' aria-hidden='true'></i>&nbsp;Upload</a>";
            }
            $res[] = [
                'id'            => $pv,
                'main'          => $main,
                'trash'         => $trash,
                'warning'       => $warning,
                'status'        => $prop->completed
            ];
        }
        return $res;
//        return [
//            'id'            => $this->property_id,
//            'main'          => $main,
//            'trash'         => $trash,
//            'warning'       => $warning,
//            'status'        => $prop->completed
//        ];
    }

}
