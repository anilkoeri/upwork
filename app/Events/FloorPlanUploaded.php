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

class FloorPlanUploaded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $property_id;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($property_id)
    {
        $this->property_id = $property_id;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
//        return new PrivateChannel('floorPlan-uploaded');
        return ['channel.floorPlan'];
    }
    public function broadcastAs()
    {
        return 'floorPlan-uploaded';
    }

    public function broadcastWith()
    {
        // This must always be an array. Since it will be parsed with json_encode()
        $prop = Property::find($this->property_id);
        $trash = "";
        $warning = "";
        if($prop->floor_file == 1){
            $main = "<a href='".url('/admin/floor_plan/property/'.$this->property_id)."' class='btn btn-xs btn-primary d-inline-block mr-1'>View</a>";
            $trash = "<a href='#' class='btn btn-xs bg-light-grey delete_amenity mr-1'><i class='fa fa-trash text-white' aria-hidden='true'></i></a>";
        }elseif($prop->floor_file == 2){
            $main = "<a href='#' class='btn btn-xs btn-warning d-inline-block mr-1'>Pending</a>";
        }elseif($prop->floor_file == 3){
            $notice = Notice::where('property_id',$this->property_id)->where('file_type','2')->first();
            $main = "<a href='".url('/admin/floor_plan/property/'.$this->property_id)."' class='btn btn-xs btn-primary d-inline-block mr-1'>View</a>";
            $trash = "<a href='#' class='btn btn-xs bg-light-grey delete_sqft'><i class='fa fa-trash text-white' aria-hidden='true'></i></a>";
            if($notice){
                $warning = "<a class='d-inline-block' href='" . url('/admin/notice/' . $notice->slug) . "' data-toggle='tooltip' data-html='true' title='The upload has some potential errors. Please click here to check which errors were detected.'><i class='fa fa-exc fa-exclamation-circle text-warning align-middle' aria-hidden='true'></i></a>";
            }
        }else{
            $main = "<a href='".url('/admin/floor_plan/create/property/'.$this->property_id)."' class='btn btn-xs btn-secondary d-inline-block mr-1'><i class='fa fa-arrow-circle-up' aria-hidden='true'></i>&nbsp;Upload</a>";
        }
        return [
            'id' => $this->property_id,
            'main' => $main,
            'trash' => $trash,
            'warning' => $warning
        ];
    }
}
