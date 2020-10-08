<?php

namespace App\Http\Controllers\Frontend;

use App\Jobs\ExportReview;
use App\Models\Amenity;
use App\Models\AmenityValue;
use App\Models\Category;
use App\Models\Notice;
use App\Models\Property;
use App\Models\Review;
use App\Models\Unit;
use App\Models\UnitAmenityValue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Http\Controllers\Controller;

use Auth,DB;

class ReviewController extends Controller
{
    /**
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index($id)
    {
        $auth_user = \Auth::user();
        $prop = Property::findOrFail($id);
        if($auth_user->company_id == $prop->company_id || $auth_user->hasRole('superAdmin')){
            $property = $prop;
        }else{
            abort('401');
        }
        $review = Review::with(['unit','amenityValue','amenityValue.amenity','amenityValue.amenity.category'])
            ->withCount(['unitAmenityValues'])
            ->where('property_id',$id)
            ->orderBy('created_at','desc')
            ->paginate(100);

//        $properties = Property::all();

        if($auth_user->hasRole('superAdmin')){
            $properties = Property::get(['id','property_name']);
        }else{
            $properties = Property::where('company_id',$auth_user->company_id)->get(['id','property_name']);
        }

//        foreach($properties as $pk => $pv){
//            if($pv->id == $id){
//                $property  = $pv;
//            }
//        }
        $categories = Category::with(['amenities','amenities.amenityValues'])->get();
        return view('frontend.review.index',compact('review','property','properties','categories'));
    }

    /**
     * update review status to accepted
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function accept($id)
    {
        $review = Review::find($id);
        $review->status = 2;
        $review->save();
        return response()->json([
            'id' => $id,
            'message' => 'Status Updated'
        ],200);
    }

    /**
     * update review status to rejected, and revert corresponding update
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject($id)
    {
        $review = Review::find($id);
        $unit_ids = array();
        switch ($review->action) {
            case "1":
                $unit_ids = json_decode($review->multiple_units,true);
                if(!empty($unit_ids)){
                    UnitAmenityValue::where('amenity_value_id',$review->amenity_value_id)
                        ->whereIn('unit_id',$unit_ids)
                        ->delete();
                }else{
                    $uav = UnitAmenityValue::where('unit_id',$review->unit_id)
                        ->where('amenity_value_id',$review->amenity_value_id)
                        ->first();
                    $uav->delete();
                }
                break;
            case "2":

                $amenityValue = AmenityValue::where('id',$review->amenity_value_id)
                    ->first();
                $amenityValue->amenity_value = $review->old_amenity_value;
                if($amenityValue->initial_amenity_value == $review->old_amenity_value){
                    $amenityValue->status = 0;
                }
                $amenityValue->save();

                break;
            case "3":
                $unit_ids = json_decode($review->multiple_units,true);
                $query = UnitAmenityValue::where('amenity_value_id',$review->amenity_value_id);
                if(!empty($unit_ids)){
                    $query->whereIn('unit_id',$unit_ids);
                }else{
                    $query->where('unit_id',$review->unit_id);
                }
                $query->restore();
                break;
            case "4":
                $unit = Unit::find($review->unit_id);
                $unit->unit_note = '';
                $unit->save();
                break;
            case "5":
                $uav = UnitAmenityValue::where('unit_id',$review->unit_id)
                    ->where('amenity_value_id',$review->amenity_value_id)
                    ->first();
                if($uav){
                    $uav->delete();
                }

                break;
        }

//        $review->status = 3;
//        $review->save();
        $review->delete();
        return response()->json([
            'id' => $id,
            'message' => 'Status Rejected',
        ],200);
    }

    /**
     * get unitAmenities details of a unit
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUnitAmenities(Request $request)
    {
//        pe($request->all());
        $unit = Unit::find($request->unit_id);

        $uav = UnitAmenityValue::with(['amenityValue','amenityValue.amenity','amenityValue.amenity.category'])
            ->where('unit_id',$request->unit_id)
            ->get();
        $unitAemnities = \View::make('frontend.review._unitAmenity')
            ->with('uav',$uav)
            ->with('unit',$unit)
            ->render();
        return response()->json([
            'unitAmenities' => $unitAemnities,
        ],200);

    }

    /**
     * getUnitList for Multiple part
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function unitList($id)
    {

        $review = Review::find($id);

        if($review->multiple_units != NULL){

            $unit_ids = json_decode($review->multiple_units,true);

            $units = Unit::whereIn('id',$unit_ids)->get();

//            $uav = UnitAmenityValue::with(['unit'])->where('amenity_value_id',$review->amenity_value_id)
//                ->whereIn('unit_id',$unit_ids)
//                ->get();

            return response()->json([
                'units' => $units
            ],200);

        }else{

            if($review->action == 2){
                $uav = UnitAmenityValue::with(['unit'])->where('amenity_value_id',$review->amenity_value_id)
                    ->get();
                return response()->json([
                    'uav' => $uav
                ],200);
            }else{
                $unit = Unit::where('id',$review->unit_id)->first();
                return response()->json([
                    'unit' => $unit
                ],200);
            }

        }

    }

    /**
     * delete unitAmenity value
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUnitAmenityValue($id)
    {
        $uav = UnitAmenityValue::find($id);
        $uav->delete();
        return response()->json([
            'sts' => '1',
            'message' => 'Successfully Deleted'
        ],200);
    }

    public function storeUnitAmenityValue(Request $request)
    {
//        pe($request->all());
        $request->validate(
            [
                'categorySelect' => 'required',
                'amenitySelect' => 'required',
                'amenityValueSelect' => 'required',
//                'unitID' => 'required'
            ],
            [
                'categorySelect.required' => 'Category Name is required',
                'amenitySelect.required' => 'Amenity Name is required',
                'amenityValueSelect.required' => 'Amenity Value Name is required',
            ]
        );
        $unitAmenityValue = UnitAmenityValue::where('unit_id',$request->unitID)
            ->where('amenity_value_id',$request->amenityValueSelect)
            ->first();
        if($unitAmenityValue){
            return response()->json([
                'errors' => [
                    'amenitySelect' => [
                        'You can\'t add duplicate amenity to the unit'
                    ]
                ]
            ],422);
        }
        $unitAmenityValue = UnitAmenityValue::Create(
            [
                'unit_id' => $request->unitID,
                'amenity_value_id' => $request->amenityValueSelect,
            ]
        );

        $unitAmenityValue=UnitAmenityValue::with(['amenityValue','amenityValue.amenity','amenityValue.amenity.category'])->where('id',$unitAmenityValue->id)->first();

        return response()->json([
            'uav' => $unitAmenityValue->id,
            'category'=> $unitAmenityValue->amenityValue->amenity->category->category_name,
            'amenity'=> $unitAmenityValue->amenityValue->amenity->amenity_name,
            'amenity_value' => $unitAmenityValue->amenityValue->amenity_value,
            'status' =>  $unitAmenityValue->amenityValue->status,
        ],200);

    }

    public function updateUnitAmenityValue(Request $request)
    {
        $request->validate([
            'am_value' => 'required|numeric',
        ]);

//        $unitAmenityValue = UnitAmenityValue::with(['amenityValue'])->where('id',$id)->first();
        $amenityValue = AmenityValue::where('id',$request->amenity_value_id)->first();

        if($amenityValue->initial_amenity_value == $request->am_value){
            $amenityValue->status = 0;
        }else{
            $amenityValue->status = 2;
        }
        $amenityValue->amenity_value = $request->am_value;
        $amenityValue->save();
        return response()->json([
            'amenity_value' => $amenityValue->amenity_value,
            'am_val_id' => $amenityValue->id
        ],200);
    }

    public function updateUnitNote(Request $request)
    {
        $unit = Unit::find($request->unit_id);
        $unit->unit_note = $request->unit_note;
        $unit->save();

        return response()->json([
            'sts' => 1,
            'unit' => $unit
        ],200);
    }

    public function export(Request $request,$property_id)
    {
        $property_name = Property::where('id',$property_id)->pluck('property_name')->first();

        $reviews = Review::with(['unit','amenityValue','amenityValue.amenity','amenityValue.amenity.category'])
            ->with(['unitAmenityValues.unit:id,unit_number'])
            ->withCount(['unitAmenityValues'])
            ->where('property_id',$property_id)
            ->orderBy('created_at','desc')
            ->get();
        if(count($reviews) > 0){
            if($request->file_type == 'pdf'){
                if($request->file_detail == 'full'){
                    $pdf = \PDF::loadView('frontend.review._fullPdf',compact('reviews'));
                }else{
                    $pdf = \PDF::loadView('frontend.review._pdf',compact('reviews'));
                }
                $file_name = 'Review - '.$property_name.' - '.date('Y_m_d').'.pdf';
                return $pdf->setPaper('a4')->setOrientation('landscape')->download($file_name);
            }else{

                $file_name = 'Review - '.$property_name.' - '.date('Y_m_d').'.xlsx';

                if($request->file_detail == 'full'){
                    $datas = $this->exportFullExcel($reviews);
                    return (new FastExcel($datas))->download($file_name);
                }else{
                    return (new FastExcel($reviews))->download($file_name,function($review){
                        $count_text = 1;
                        $unit_lists = '';
                        $unit_ids = $unitAmvalues = array();
                        if($review->action == 1) {
                            $action = 'Added';
                            if($review->multiple_units != NULL){
                                $unit_ids = json_decode($review->multiple_units, true);
                                $count_text = count($unit_ids);
                                foreach($unit_ids as $uk => $uv){
                                    if($uk != 0){
                                        $unit_lists = $unit_lists.', ';
                                    }
                                    $unit_lists = $unit_lists.$uv;
                                }

                            }
                        }elseif($review->action == 2){
                            $action = 'Updated';
                            $count_text = $review->unit_amenity_values_count ;
                        }elseif($review->action == 3){
                            $action = 'Deleted';
                            if($review->multiple_units != NULL){
                                $unit_ids = json_decode($review->multiple_units, true);
                                $count_text = count($unit_ids);

                                foreach($unit_ids as $uk => $uv){
                                    if($uk != 0){
                                        $unit_lists = $unit_lists.', ';
                                    }
                                    $unit_lists = $unit_lists.$uv;
                                }
                            }
                        }elseif($review->action == 4){
                            $action = 'Note';
                        }else{
                            $action = 'Amenity (New)';
                            $count_text = 0;
                        }

                        if($count_text != 0){

                            if($review->multiple_units != NULL){
                                $unit_num = $unit_lists;
                            }else{
                                if($count_text > 1) {
                                    $str = '';
                                    $unitAmvalues = $review->unitAmenityValues;
                                    foreach ($unitAmvalues as $uak => $uav) {
                                        if ($uak != 0) {
                                            $str = $str . ', ';
                                        }
                                        $str = $str . $uav->unit->unit_number;
                                    }
                                    $unit_num = $str; //'Multiple';
                                }else{
                                    $unit_num = $review->unit->unit_number;
                                }
                            }
                        }else{
                            $unit_num = '';
                        }

                        return [
                            'Units' => $count_text,
                            'Unit' => $unit_num,
                            'Category' => ($review->amenityValue) ? $review->amenityValue->amenity->category->category_name : '',
                            'Amenity' => ($review->amenityValue) ? $review->amenityValue->amenity->amenity_name : '',
                            'New Amenity' => $review->new_amenity_value,
                            'Old Amenity' => $review->old_amenity_value,
                            'Action' => $action,
                            'Note' => $review->unit_note,
                            'Status' => ($review->status == 2)?'Accepted':''
                        ];

                    });
                }
            }


        }else{
            return back()->withErrors('Nothing to export');
        }



    }

    private function exportFullExcel($reviews)
    {
        $datas = $reviews->flatMap(function ($review) {
            $items = [];
            $count_text = 1;
            $unit_ids = $unitAmvalues = array();

            if($review->action == 1) {
                $action = 'Added';
                if($review->multiple_units != NULL){
                    $unit_ids = json_decode($review->multiple_units, true);
                    $count_text = count($unit_ids);
                }
            }elseif($review->action == 2){
                $action = 'Updated';
                $count_text = $review->unit_amenity_values_count ;
            }elseif($review->action == 3){
                $action = 'Deleted';
                if($review->multiple_units != NULL){
                    $unit_ids = json_decode($review->multiple_units, true);
                    $count_text = count($unit_ids);
                }
            }elseif($review->action == 4){
                $action = 'Note';
            }else{
                $action = 'Amenity (New)';
                $count_text = 0;
            }

            if($count_text != 0){
                if($review->multiple_units != NULL){
                    foreach ($unit_ids as $uk => $uv) {
                        $items[] = [
                            'Units' => $count_text,
                            'Unit' => $uv,
                            'Category' => ($review->amenityValue) ? $review->amenityValue->amenity->category->category_name : '',
                            'Amenity' => ($review->amenityValue) ? $review->amenityValue->amenity->amenity_name : '',
                            'New Amenity' => $review->new_amenity_value,
                            'Old Amenity' => $review->old_amenity_value,
                            'Action' => $action,
                            'Note' => $review->unit_note,
                            'Status' => ($review->status == 2)?'Accepted':''
                        ];
                    }
                }else{
                    if($count_text > 1) {
                        $unitAmvalues = $review->unitAmenityValues;
                        foreach ($unitAmvalues as $uak => $uav) {
                            $items[] = [
                                'Units' => $count_text,
                                'Unit' => $uav->unit->unit_number,
                                'Category' => ($review->amenityValue) ? $review->amenityValue->amenity->category->category_name : '',
                                'Amenity' => ($review->amenityValue) ? $review->amenityValue->amenity->amenity_name : '',
                                'New Amenity' => $review->new_amenity_value,
                                'Old Amenity' => $review->old_amenity_value,
                                'Action' => $action,
                                'Note' => $review->unit_note,
                                'Status' => ($review->status == 2)?'Accepted':''
                            ];
                        }
                    }else{
                        $items[] = [
                            'Units' => $count_text,
                            'Unit' => $review->unit->unit_number,
                            'Category' => ($review->amenityValue) ? $review->amenityValue->amenity->category->category_name : '',
                            'Amenity' => ($review->amenityValue) ? $review->amenityValue->amenity->amenity_name : '',
                            'New Amenity' => $review->new_amenity_value,
                            'Old Amenity' => $review->old_amenity_value,
                            'Action' => $action,
                            'Note' => $review->unit_note,
                            'Status' => ($review->status == 2)?'Accepted':''
                        ];
                    }
                }
            }else{
                $items[] = [
                    'Units' => $count_text,
                    'Unit' => '',
                    'Category' => ($review->amenityValue) ? $review->amenityValue->amenity->category->category_name : '',
                    'Amenity' => ($review->amenityValue) ? $review->amenityValue->amenity->amenity_name : '',
                    'New Amenity' => $review->new_amenity_value,
                    'Old Amenity' => $review->old_amenity_value,
                    'Action' => $action,
                    'Note' => $review->unit_note,
                    'Status' => ($review->status == 2)?'Accepted':''
                ];;
            }
            return $items;
        });
        return $datas;


    }

    function reviewsGenerator($property_id) {

        foreach (Review::with(['unit','amenityValue','amenityValue.amenity','amenityValue.amenity.category','amenityValue.unitAmenityValues'])
                     ->where('property_id',$property_id)
                     ->orderBy('created_at','desc')
                     ->get() as $review) {
                        yield $review;
//            pe($review);
        }
    }

    /**
     * accept all reviews of a property
     */
    public function acceptAll($id)
    {
        $auth_user = \Auth::user();
        $prop = Property::findOrFail($id);
        if($auth_user->company_id == $prop->company_id || $auth_user->hasRole('superAdmin')){
            $property = $prop;
        }else{
            abort('401');
        }
        Review::where('property_id',$id)->where('status',1)->update(['status' => 2]);

        return redirect('review/property/'.$id)->with('success','Successfully Accepted All');
    }

    public function rejectAll(Request $request, $id)
    {
        $auth_user = \Auth::user();
        $prop = Property::findOrFail($id);
        if($auth_user->company_id == $prop->company_id || $auth_user->hasRole('superAdmin')){
            $property = $prop;
        }else{
            abort('401');
        }

        $reviews = Review::where('property_id',$id)->where('status',1)->orderBy('id','desc')->get();
//        pe($reviews);
        $unit_ids = array();
        foreach($reviews as $review){
            switch ($review->action) {
            case "1":
                $unit_ids = json_decode($review->multiple_units,true);
                if(!empty($unit_ids)){
                    UnitAmenityValue::where('amenity_value_id',$review->amenity_value_id)
                        ->whereIn('unit_id',$unit_ids)
                        ->delete();
                }else{
                    $uav = UnitAmenityValue::where('unit_id',$review->unit_id)
                        ->where('amenity_value_id',$review->amenity_value_id)
                        ->first();
                    $uav->delete();
                }
                break;
            case "2":

                $amenityValue = AmenityValue::where('id',$review->amenity_value_id)
                    ->first();
                $amenityValue->amenity_value = $review->old_amenity_value;
                if($amenityValue->initial_amenity_value == $review->old_amenity_value){
                    $amenityValue->status = 0;
                }
                $amenityValue->save();

                break;
            case "3":
                $unit_ids = json_decode($review->multiple_units,true);
                $query = UnitAmenityValue::where('amenity_value_id',$review->amenity_value_id);
                if(!empty($unit_ids)){
                    $query->whereIn('unit_id',$unit_ids);
                }else{
                    $query->where('unit_id',$review->unit_id);
                }
                $query->restore();
                break;
            case "4":
                $unit = Unit::find($review->unit_id);
                $unit->unit_note = '';
                $unit->save();
                break;
            case "5":
                $uav = UnitAmenityValue::where('unit_id',$review->unit_id)
                    ->where('amenity_value_id',$review->amenity_value_id)
                    ->first();
                if($uav){
                    $uav->delete();
                }

                break;
        }
            $review->delete();
        }
//        $review->status = 3;
//        $review->save();

//        return response()->json([
//            'id' => $id,
//            'message' => 'Status Rejected',
//        ],200);


        return redirect('review/property/'.$id)->with('success','Successfully Rejected');

    }

}
