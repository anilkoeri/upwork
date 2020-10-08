<?php

namespace App\Http\Controllers\Admin;

use App\Models\AmenityValue;
use App\Models\Category;
use App\Models\Property;
use App\Models\Review;
use App\Models\Unit;
use App\Models\UnitAmenityValue;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use DB;
use Rap2hpoutre\FastExcel\FastExcel;

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

        if($auth_user->hasRole('superAdmin')){
            $properties = Property::get(['id','property_name']);
        }else{
            $properties = Property::where('company_id',$auth_user->company_id)->get(['id','property_name']);
        }
//        $categories = Category::with(['amenities','amenities.amenityValues'])->get();

        $categories = Category::with(['amenities' => function($query) use($property){
            $query->where('property_id',$property->id)->orderBy('amenity_name');
        },'amenities.amenityValues'])
            ->orderBy('category_name')
            ->where('company_id',$prop->company_id)
            ->orWhere('global','1')
            ->get();

        return view('admin.review.index',compact('review','property','properties','categories'));
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
                UnitAmenityValue::where('amenity_value_id',$review->amenity_value_id)
                    ->whereIn('unit_id',$unit_ids)
                    ->forceDelete();
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
                $query->whereIn('unit_id',$unit_ids);
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
     * accept all reviews of a property
     * @param $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
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

        return redirect('admin/review/property/'.$id)->with('success','Successfully Accepted All');
    }

    /**
     * Reject all changes of a property
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
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
//                    if(!empty($unit_ids)){
                        UnitAmenityValue::where('amenity_value_id',$review->amenity_value_id)
                            ->whereIn('unit_id',$unit_ids)
                            ->forceDelete();
//                    }else{
//                        $uav = UnitAmenityValue::where('unit_id',$review->unit_id)
//                            ->where('amenity_value_id',$review->amenity_value_id)
//                            ->first();
//                        $uav->delete();
//                    }
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
//                    if(!empty($unit_ids)){
                        $query->whereIn('unit_id',$unit_ids);
//                    }else{
//                        $query->where('unit_id',$review->unit_id);
//                    }
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


        return redirect('admin/review/property/'.$id)->with('success','Successfully Rejected');

    }

    /**
     * get unitAmenities details of a unit
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listUnitAmenities(Request $request, $unit_id)
    {
//        pe($request->all());
//        $amenities_ids = DB::table('units_amenities_values as uav')
//            ->join('amenity_values as av', 'av.id', '=', 'uav.amenity_value_id')
//            ->where('uav.unit_id', $unit_id)
////            ->where('av.property_id',$request->property_id)
//            ->whereNull('deleted_at')
//            ->distinct()
//            ->pluck('av.amenity_id')
//            ->toArray();
//
//        $amenities = DB::table('amenities as a')
//            ->join('amenity_values as av', 'av.amenity_id', 'a.id')
//            ->join('categories as c', 'c.id', '=', 'a.category_id')
//            ->select('a.id', 'a.amenity_name', 'av.id as av_id', 'av.initial_amenity_value', 'av.amenity_value', 'av.status', 'c.id as c_id', 'c.category_name')
//            ->whereIn('a.id', $amenities_ids)
//            ->where('a.property_id',$request->property_id)
//            ->whereNull('a.deleted_at')
//            ->orderBy('c.category_name', 'asc')
//            ->orderBy('a.amenity_name', 'asc')
//            ->get();

        $amenities = DB::table('units_amenities_values as uav')
            ->join('amenity_values as av', 'av.id', '=', 'uav.amenity_value_id')
            ->join('amenities as a', 'a.id', '=', 'av.amenity_id')
            ->join('categories as c', 'c.id', '=', 'a.category_id')
            ->where('uav.unit_id', $unit_id)
            ->select('a.id', 'a.amenity_name', 'av.id as av_id', 'av.initial_amenity_value', 'av.amenity_value', 'av.status', 'c.id as c_id', 'c.category_name')
            ->whereNull('uav.deleted_at')
            ->whereNull('a.deleted_at')
//            ->distinct()
            ->orderBy('c.category_name', 'asc')
            ->orderBy('a.amenity_name', 'asc')
            ->get();
//
//        pe($amenities);
        $unit = Unit::find($unit_id);

        $unitAemnities = \View::make('admin.review._unitAmenityLists')
            ->with('amenities', $amenities)
            ->with('unit', $unit)
            ->render();

//        pe($amenity_id);
//

//
//        $uav = UnitAmenityValue::with(['amenityValue','amenityValue.amenity','amenityValue.amenity.category'])
//            ->where('unit_id',$unit_id)
//            ->get();
//        $unitAemnities = \View::make('admin.review._editUnitBlock')
//            ->with('uav',$uav)
//            ->with('unit',$unit)
//            ->render();
        return response()->json([
            'unitAmenities' => $unitAemnities,
            'unit' => $unit
        ],200);

    }

    /**
     * delete unitAmenity value
     * @param Request $request
     * @param $unit_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAmenityFromUnit(Request $request,$unit_id)
    {
        $uav = UnitAmenityValue::where('amenity_value_id',$request->av_id)
            ->where('unit_id',$unit_id)
            ->first();
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
        $unitAmenityValue = UnitAmenityValue::withTrashed()->where('unit_id',$request->unitID)
            ->where('amenity_value_id',$request->amenityValueSelect)
            ->first();
        if($unitAmenityValue){
            if($unitAmenityValue->trashed()){
                $unitAmenityValue->restore();
            }else{
                return response()->json([
                    'errors' => [
                        'amenitySelect' => [
                            'You can\'t add duplicate amenity to the unit'
                        ]
                    ]
                ],422);
            }
        }else{
            $unitAmenityValue = UnitAmenityValue::Create(
                [
                    'unit_id' => $request->unitID,
                    'amenity_value_id' => $request->amenityValueSelect,
                ]
            );
        }

        $unitAmenityValue = UnitAmenityValue::with(['amenityValue','amenityValue.amenity','amenityValue.amenity.category'])->where('id',$unitAmenityValue->id)->first();

        if($unitAmenityValue->amenityValue->status == 2){
            $text_color = 'text-updated';
        }else{
            $text_color = '';
        }

//        $response = [
//            'id' => $unitAmenityValue->amenityValue->amenity_id,
//            'unit_id' => $unitAmenityValue->unit_id,
//            'amenity_name' => $unitAmenityValue->amenityValue->amenity->amenity_name,
//            'av_id' => $unitAmenityValue->amenityValue,
//            'text_color' => $text_color,
//            'initial_amenity_value' => $unitAmenityValue->amenityValue->initial_amenity_value,
//            'amenity_value' => $unitAmenityValue->amenityValue->amenity_value,
//            'c_id' => $unitAmenityValue->amenityValue->amenity->category_id,
//            'category_name' => $unitAmenityValue->amenityValue->amenity->category->category_name,
//        ];

//        $amenities = DB::table('amenities as a')
//            ->join('amenity_values as av', 'av.amenity_id', 'a.id')
//            ->join('categories as c', 'c.id', '=', 'a.category_id')
//            ->select('a.id', 'a.amenity_name', 'av.id as av_id', 'av.initial_amenity_value', 'av.amenity_value', 'av.status', 'c.id as c_id', 'c.category_name')
//            ->where('a.id', $amenities_ids)
//            ->where('av.property_id',$request->property_id)
//            ->orderBy('c.category_name', 'asc')
//            ->orderBy('a.amenity_name', 'asc')
//            ->get();

        return response()->json([
            'id' => $unitAmenityValue->amenityValue->amenity_id,
            'unit_id' => $unitAmenityValue->unit_id,
            'amenity_name' => $unitAmenityValue->amenityValue->amenity->amenity_name,
            'av_id' => $unitAmenityValue->amenityValue,
            'text_color' => $text_color,
            'initial_amenity_value' => $unitAmenityValue->amenityValue->initial_amenity_value,
            'amenity_value' => $unitAmenityValue->amenityValue->amenity_value,
            'c_id' => $unitAmenityValue->amenityValue->amenity->category_id,
            'category_name' => $unitAmenityValue->amenityValue->amenity->category->category_name,
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
            'am_val_id' => $amenityValue->id,
            'am_id' => $amenityValue->amenity_id
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
            // ->limit('16')
            ->get();

        if(count($reviews) > 0){
            if($request->file_type == 'pdf'){
                if($request->file_detail == 'full'){
                    $pdf = \PDF::loadView('admin.review._fullPdf',compact('reviews'));
                }else{
                    $pdf = \PDF::loadView('admin.review._pdf',compact('reviews'));
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
                                    $un_num = Unit::find($uv)->unit_number;
                                    if($uk != 0){
                                        $unit_lists = $unit_lists.', ';
                                    }
                                    $unit_lists = $unit_lists . $un_num;
                                }

                            }
                        }elseif($review->action == 2){
                            $action = 'Updated';
                            $count_text = $review->unit_amenity_values_count;
                        }elseif($review->action == 3){
                            $action = 'Deleted';
                            if($review->multiple_units != NULL){
                                $unit_ids = json_decode($review->multiple_units, true);
                                $count_text = count($unit_ids);

                                foreach($unit_ids as $uk => $uv){
                                    $un_num = Unit::find($uv)->unit_number;
                                    if($uk != 0){
                                        $unit_lists = $unit_lists.', ';
                                    }
                                    $unit_lists = $unit_lists. $un_num;
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
                                            $str .= ', ';
                                        }
                                        $str .= ($uav->unit)? $uav->unit->unit_number:'';
                                    }
                                    $unit_num = $str; //'Multiple';
                                }else{
                                    $unit_num = ($review->unit)?$review->unit->unit_number:'';
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
                            'Units' => ($count_text != 0)?1:$count_text,
                            'Unit' => Unit::find($uv)->unit_number,
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
                                'Unit' => ($uav->unit) ? $uav->unit->unit_number : '',
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
                            'Unit' => ($review->unit)? $review->unit->unit_number:'',
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

}
