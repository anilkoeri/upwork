<?php

namespace App\Http\Controllers;

use App\Exports\ExportFormattedCSV;
use App\Imports\FormatCSV;
use App\Mail\CSVImportJobCompleted;
use App\Models\Amenity;
use App\Models\AmenityCategoryMapping;
use App\Models\AmenityPricingReview;
use App\Models\AmenityValue;
use App\Models\Building;
use App\Models\Category;
use App\Models\Floor;
use App\Models\FloorPlan;
use App\Models\MappingTemplate;
use App\Models\Notice;
use App\Models\Permission;
use App\Models\Review;
use App\Models\Unit;
use App\Models\UnitAmenityValue;
use App\Models\User;
use Illuminate\Http\Request;

use App\Http\Services\AmenityService;
use App\Models\Property;
use Carbon\Carbon;
use DB,Mail,Excel;

use League\Csv\Reader;
use League\Csv\Statement;
use Rap2hpoutre\FastExcel\FastExcel;

class TestController extends Controller
{
    private $data,$error_arr,$error_row_numbers;

    public function __construct(){
        $this->data = array(
            'user_id' => '2',
            'user_email' => 'thesarojstha@gmail.com',
            'user_name' => 'Saroj Gmail',
            'url' => 'http://localhost/amenity/public/admin/property',
            'row_value' => '2',
            'offset' => '0',
            'limit' => '200',
            'file_name' => 'AmenityUnit.csv',
        );

//        $this->error_arr = $error_arr;
//        $this->error_row_numbers = $error_row_numbers;

    }

    public function storeProperty()
    {

        $map_data = array();
        $map_data[0] = 'property_name';
        $map_data[1] = 'floor_plan_code';
        $map_data[2] = 'floor_plan_group_name';
        $map_data[3] = 'floor_plan_rentable_square';
        $map_data[4] = 'floor_plan_brochure_name';
        $map_data[5] = 'building_name';
        $map_data[6] = 'unit_number';
        $map_data[7] = 'effective_date';
        $map_data[8] = 'inactive_date';
        $map_data[9] = 'amenity_name';
        $map_data[10] = 'amenity_value';

        $offset = $this->data['offset'];
        $limit = $this->data['limit'];

        $filename = $this->data['file_name'];
        $service = new AmenityService();
        $dbase = new Property();

        // $skip = $this->data['skip'];
//        $map_data = $this->data['map_data'];


        $db_header_obj = new Property();
        $db_header = $db_header_obj->getTableColumns();

        $csv_file_path = storage_path('app/files/property/').$filename;
        if (!ini_get("auto_detect_line_endings")) {
            ini_set("auto_detect_line_endings", TRUE);
        }
        $csv = Reader::createFromPath($csv_file_path, 'r');
        $csv->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');

//        $csv->setOutputBOM(Reader::BOM_UTF8);
//        $csv->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');

        $csv->setHeaderOffset(0);
        $csv_header = $csv->getHeader();



        $rec_arr = array();
        $records = array();
        $records_arr = array();
        $properties_arr = array();

        $stmt = (new Statement())
            ->offset($offset)
            ->limit($limit)
        ;

        $records = $stmt->process($csv);

        foreach ($records as $record)
        {
            $rec_arr[] = array_values($record);
        }

        $records_arr = $service->trimArray($rec_arr);

        if(count($records_arr)>0)
        {
            foreach($records_arr as $ck => $cv) {

                $existing = NULL;
                $property_arr = array();
                foreach ($map_data as $mk => $mv) {
                    if (isset($mv)) {
                        $property_arr[$mv] = $cv[$mk];
                    }
                }
                $property_arr['created_at'] = Carbon::now();
                $property_arr['updated_at'] = Carbon::now();

//                $properties_arr[] = $property_arr;
//                pe($property_arr);

                try{
                    $property = Property::firstOrCreate(
                        ['property_name' => $property_arr['property_name']]
                    );
                    $building = Building::firstOrCreate(
                        [
                            'building_name' => $property_arr['building_name'],
                            'property_id' => $property->id,
                        ]
                    );
                    $floor = Floor::firstOrCreate(
                        [
                            'floor_plan_code' => $property_arr['floor_plan_code'],
                            'building_id' => $building->id
                        ],
                        [
                            'floor_plan_group_name' => $property_arr['floor_plan_group_name'],
                            'floor_plan_rentable_square' => $property_arr['floor_plan_rentable_square'],
                            'floor_plan_brochure_name' => $property_arr['floor_plan_brochure_name'],
                        ]
                    );
                    $unit = Unit::firstOrCreate(
                        [
                            'unit_number' => $property_arr['unit_number'],
                            'floor_id' => $floor->id,
                        ]
                    );
                    $amenity = Amenity::updateOrCreate(
                        [
                            'unit_id' => $unit->id,
                            'amenity_name' => trim($property_arr['amenity_name'])
                        ],
                        [
                            'amenity_value' => $property_arr['amenity_value'],
                            'effective_date' => $property_arr['effective_date'],
                            'inactive_date' => $property_arr['inactive_date']
                        ]
                    );
                } catch (\Exception $e) {
//                    $this->error_arr[] = $e->getMessage();
//                    $this->error_row_numbers[] = $this->data['row_value'];
                }

                $this->data['row_value'] = $this->data['row_value'] + 1;
            }

//            Property::insert($properties_arr);



            $this->data['offset'] = $offset + $limit;

//            $error_arr = array();
//            $error_row_numbers = array();
//            $propertyInsertJob = (new StoreProperty($this->data,$this->error_arr,$this->error_row_numbers))->delay(Carbon::now()->addSeconds(3));
//            dispatch($propertyInsertJob);

        }else{

            $arr_data = [
                'filename' => $filename,
                'user_name' => $this->data['user_name'],
                'error' => $this->error_arr,
                'error_row_numbers' => $this->error_row_numbers
            ];
            // DB::statement('EXEC procUpdateSortBaseVoterSubId');
//            Mail::to($this->data['user_email'])->send(new CSVImportJobComplete($arr_data));

        }



        if (!ini_get("auto_detect_line_endings")) {
            ini_set("auto_detect_line_endings", FALSE);
        }
    }

    public function testEmail()
    {
        $error_arr = ['this is error','2nd Error'];
        $error_row_numbers = ['20','50'];
        $arr_data = [
            'filename' => 'ABC.csv',
            'user_name' => 'thesarojstha@gmail.com',
            'error' => $error_arr,
            'error_row_numbers' => $error_row_numbers
        ];
        // DB::statement('EXEC procUpdateSortBaseVoterSubId');
        Mail::to($this->data['user_email'])->send(new CSVImportJobCompleted($arr_data));
    }

    public function exportTest()
    {

        $reviews = DB::table('reviews')->where('property_id',1)->get()->flatMap(function ($review) {
            $items = [];

            if ($review->multiple_units != NULL) {
                $unit_ids = json_decode($review->multiple_units, true);

                foreach ($unit_ids as $uk => $uv) {
                    $items[] = [
                        'Name' => $review->amenity_value_id,
                        'Units' => $uv
                    ];
                }
            }

            return $items;
        });

        $file_name = 'Review - '.date('Y_m_d').'.xlsx';

        return (new FastExcel($reviews))->download($file_name);



//        $reviews = DB::table('reviews')->get();

//        $file_name = 'Review - '.date('Y_m_d').'.xlsx';
//
//        return (new FastExcel($reviews))->download($file_name,function($review){
//            $unit_lists = '';
//            if($review->multiple_units != NULL){
////                $unit_ids = json_decode($review->multiple_units, true);
////
////                foreach($unit_ids as $uk => $uv){
////                    return [
////                        'Name' => $review->name,
////                        'Units' => $uv
////                    ];
////                }
////
////            }


//            foreach ($reviews as $review) {
//                # code...
//                if(!empty($review->multiple_units)) {
//
//                    $unit_ids = json_decode($review->multiple_units, true);
//
//                    foreach($unit_ids as $uk => $uv){
//                        return [
//                            'Name' => $review->name,
//                            'Units' => $uv
//                        ];
//                    }
//
//                }
//            }
//
//
//
//
//        });

    }

    function reviewsGenerator()
    {
        foreach (Review::with(['unit','amenityValue','amenityValue.amenity','amenityValue.amenity.category','amenityValue.unitAmenityValues'])
                     ->where('property_id',2)
                     ->orderBy('created_at','desc')
                     ->get() as $review) {
            yield $review;
        }
    }

    public function test()
    {

        $categories = Category::orderBy('category_name','asc')->get(['id','category_name','global','company_id'])->toArray();
        pe($categories);
    }

    public function testListAmenities()
    {
        $amenities = Amenity::orderBy('amenity_name','asc')->get(['id','amenity_name','category_id'])->toArray();
        pe($amenities);
    }

    /**
     * update old category_id to new categoty_id of each amenity from amenities table
     */
    public function updateCategoryIdToNewOne()
    {
        exit();
        $temp_arr = [
        '192','219','232','259','272','298','325','338','351',
        '285',
        '182','206','209','222','249','262','275','288','315','328','341','203',
        '191','218','231','258','271','284','297','324','337','350',
        '202',
        '181','208','221','248','261','274','287','314','327','340',
        '205','189','216','229','256','269','282','295','322','335','348','204','201',
        '358',
        '190','194','217','230','257','270','283','296','323','336','349',
        '200',
        '199',
        '184','198','211','224','251','264','277','290','317','330','343','354',
        '355',
        '186','213','226','253','266','279','292','319','332','187','214','227','254','267','280','293','320','333','346','188','195','215','228','255','268','281','294','321','334','347','357','353',
        '185','212','225','252','265','278','291','318','331','344',
        '197','356','183','196','210','223','250','263','276','289','316','329','342'];

//        $amenities = Amenity::whereIn('category_id',$temp_arr)->get();

//        Category::whereIn('id',$temp_arr)->delete();
//        echo "deleted";
//        exit();

//
//        if ( count($temp_arr) === count(array_unique($temp_arr)) )
//        {
//            echo "all equal";
//        }else{
//            echo "some duplicate";
//        }
//        exit();

        $arr = [
            //Unclear <- #n/a, fake, unclear
            '388' => [
                '192','219','232','259','272','298','325','338','351',
                '285',
                '182','206','209','222','249','262','275','288','315','328','341',
            ],
            //ADA <- ADA
            '373' => ['203'],
            //Affordable <-
            '374' => [],
            //Rent <- bad, negative, rent, utilities
            '386' => [
                '191','218','231','258','271','284','297','324','337','350',
                '202',
                '181','208','221','248','261','274','287','314','327','340',
                '205'
            ],
            //Balcony <- balcony
            '375' => ['189','216','229','256','269','282','295','322','335','348'],
            //Bathroom <- bath
            '376' => ['204'],
            //Ceiling <-
            '377' => [],
            //Closet/Storage <- storage,
            '378' => ['201'],
            //Corner <-
            '379' => [],
            //Unit Features <- Feature, unit feature
            '389' => [
                        '358',
                        '190','194','217','230','257','270','283','296','323','336','349',
                    ],
            //Finishes <- Finish,Flooring
            '382' => [
                '200',
                '199'
            ],
            //Floor Level <- floor, level, penthouse
            '380' => [
                '184','198','211','224','251','264','277','290','317','330','343','354',
                '355',
                '186','213','226','253','266','279','292','319','332'
            ],
            //Floor Plan or Layout <- floorplan
            '381' => ['187','214','227','254','267','280','293','320','333','346'],
            //Kitchen <-
            '383' => [],
            //Location <- location
            '384' => ['188','195','215','228','255','268','281','294','321','334','347','357'],
            //Square Feet <- offset, size, sqft
            '387' => [
                '353',
                '185','212','225','252','265','278','291','318','331','344',
                '197'
            ],
            //Renovation <- Reno
            '385' => ['356'],
            //Unit Type <-
            '390' => [],
            //View/Exposure <- view
            '391' => ['183','196','210','223','250','263','276','289','316','329','342'],
            //Windows <-
            '392' => []
        ];
        foreach($arr as $ak => $av){
            Amenity::whereIn('category_id',$av)->update(['category_id' => $ak]);
        }

        echo "updated";
        exit();
//        exit();
//        $data = DB::table('categories')->where('global',1)->orderBy('category_name')->pluck('category_name','id')->toArray();
//        $list = array();
//        foreach($data as $dk => $dv){
//            $categories_list = array();
//            $categories_list = Category::where('global',0)
//                    ->where('category_name',$dv)
//                    ->get();
//            foreach($categories_list as $ck => $cv){
//                $list[$dk][] = $cv->id;
//            }
//        }
//
//        pe($list);
    }

//
//    public function testStorageFile()
//    {
//        $path = storage_path('app/files/property/');
//        $files = scandir($path);
//        pe($files);
//
//
//        return response()->download($path);
//    }
//
//    public function removeUnnecessaryAmenity()
//    {
//        foreach (Amenity::withCount('amenityValues')->cursor() as $amenity) {
//            if($amenity->amenity_values_count == 0){
//                $amenity->delete();
//            }
////            p($amenity->amenity_values_count);
//        }
//    }

//    public function populatePropertyToAmenity()
//    {
//        $grouped = AmenityValue::with('amenity')
//            ->orderBy('amenity_id')
//            ->get()
//            ->groupBy('amenity_id');
//
//        foreach($grouped as $gk => $gv){
//            $count = count($gv);
//            if($count > 1){
//                foreach($gv as $gvk => $gvv){
//                    if($gvk != 0){
//                        $n_am = Amenity::firstOrCreate([
//                            'amenity_code' => $gvv->amenity->amenity_code,
//                            'amenity_name' => $gvv->amenity->amenity_name,
//                            'effective_date' => $gvv->amenity->effective_date,
//                            'inactive_date' => $gvv->amenity->inactive_date,
//                            'brochure_flag' => $gvv->amenity->brochure_flag,
//                            'category_id' => $gvv->amenity->category_id,
//                            'property_id' => $gvv->property_id,
//                        ]);
//                        $am_val = AmenityValue::find($gvv->id);
//                        $am_val->amenity_id = $n_am->id;
//                        $am_val->save();
//                    }else{
//                        $n_am = Amenity::find($gvv->amenity_id);
//                        $n_am->property_id = $gvv->property_id;
//                        $n_am->save();
//                    }
//                }
//            }
//        }
//
//        echo "updated";
//        exit();
//
//    }

    /** Test For Server */

    public function updateFloorPlan()
    {
        FloorPlan::whereNull('floor_plan')->update(['floor_plan' => '']);
        FloorPlan::whereNull('unit_count')->update(['unit_count' => '0']);

        echo "updated";
        exit();
    }

    public function populatePropertyIdToAmenities()
    {
        $amenities = Amenity::with('category')->whereNull('property_id')->get();
        foreach($amenities as $ak => $av){
            $av->property_id = $av->category->property_id;
            $av->save();
        }
        echo "updated populatePropertyIdToAmenities";
        exit();
    }

    public function populateCompanyIdToCategories()
    {
        $categories = Category::with('property')->get();
        foreach($categories as $ck => $cv){
            $cv->company_id = $cv->property->company_id;
            $cv->save();
        }
        echo "updated populateCompanyIdToCategories";
        exit();
    }

    public function listGlobalCategories()
    {
        $categories = Category::where('global',1)->orderBy('category_name','asc')->get(['id','category_name'])->unique('category_name')->toArray();
        $cat = array_values($categories);
        pe($cat);
    }

    public function listNonGlobalCategories()
    {
        $categories = Category::where('global',0)->orderBy('category_name','asc')->pluck('category_name');
//        $categories = Category::where('global',0)->orderBy('category_name','asc')->get(['id','category_name'])->unique('category_name')->toArray();
//        $cat = array_values($categories);
        pe($categories);
    }

    public function updateCategoryIdToGlobalCategoryInAmenitiesTable()
    {
        $arr = [
            //ADA <- ADA
            '368' => [
                '344','357'
            ],
            //Affordable <- affordable
            '369' => ['49'],
            //Balcony <- balcony, patio
            '370' => [
                '346','354','189','241','246','59','362','176',
                '137','152','61'
            ],
            //Bathroom <- bathroom
            '371' => [
                '323','179','240','248'
            ],
            //Ceiling <- ceilings
            '372' => [
                '50'
            ],
            //Closet/Storage <- closet/storage, storage
            '373' => [
                '195',
                '366'
            ],
            //Corner <- corner
            '374' => [
                '53'
            ],
            //Finishes <- finish, flooring,
            '377' => [
                '351','262','279','51','185',
                '45','301','306','184'
            ],
            //Floor Level <- floor, floor level, level,
            '375' => [
                '4','11','19','25','302','47','82','343','353','115','121','128',
                '261','277','282','310','325','360','177','192','194','204','236','244','254',
                '5','12','20','26','79','116','122','129'
            ],
            //Floor Plan or Layout <- floor plan, layout
            '376' => [
                '138',
                '55','249',
            ],
            //Kitchen <- Appliances, kitchen
            '378' => [
                '155',
                '316','322','198'
            ],
            //Location <- location
            '379' => [
                '259','7','15','22','278','27','284','46','304','309','83','347','361','118','125','132','135','149','188','197','235','245','255'
            ],
            //Renovation <-
            '380' => [
                '258','6','13','21','283','29','312','321','80','117','123','131','187','202','238','253','300','363','174'
            ],
            //Rent <- discount, loss leader, rent
            '381' => [
                '183','200','239',
                '60','350',
                '260','276','281','313','320','349','355','178','191','196','203','234','243','252'
            ],
            //Square Feet <- sqft
            '382' => [
                '54','314'
            ],
            //Unclear <- unclear
            '383' => [
                '48','307','158'
            ],
            //Unit Features <- feature, unit feature, wd, garage
            '384' => [
                '8','14','18','28','299','78','144','124','130','134',
                '345','285','57','315','324','367','175','190','199','205','251',
                '181',
                '298','311','156'
            ],
            //Unit Type <-
            '385' => [],
            //View/Exposure <- view
            '386' => [
                '256','263','305','56','317','326','348','358','365','136','151','237','247','180'
            ],
            //Windows <- windows
            '387' => [
                '58','182'
            ]

        ];
        foreach($arr as $ak => $av){
            Amenity::whereIn('category_id',$av)->update(['category_id' => $ak]);
        }

        echo "updateCategoryIdToGlobalCategoryInAmenitiesTable";
        exit();
    }

    public function listAmenitiesWithOldCategoryId()
    {
        $arr = [
                '344','357',
                '49',
                '346','354','189','241','246','59','362','176',
                '137','152','61',
                '323','179','240','248',
                '50',
                '195',
                '366',
                '53',
                '351','262','279','51','185',
                '45','301','306','184',
                '4','11','19','25','302','47','82','343','353','115','121','128',
                '261','277','282','310','325','360','177','192','194','204','236','244','254',
                '5','12','20','26','79','116','122','129',
                '138',
                '55','249',
                '155',
                '316','322','198',
                '259','7','15','22','278','27','284','46','304','309','83','347','361','118','125','132','135','149','188','197','235','245','255',
                '258','6','13','21','283','29','312','321','80','117','123','131','187','202','238','253','300','363','174',
                '183','200','239',
                '60','350',
                '260','276','281','313','320','349','355','178','191','196','203','234','243','252',
                '54','314',
                '48','307','158',
                '8','14','18','28','299','78','144','124','130','134',
                '345','285','57','315','324','367','175','190','199','205','251',
                '181',
                '298','311','156',
                '256','263','305','56','317','326','348','358','365','136','151','237','247','180',
                '58',
                '182'
        ];
        $amenities = Amenity::whereIn('category_id',$arr)->get();
        pe($amenities);

    }

    public function removeOldCategoriesGlobalDuplicate()
    {
        $arr = [
            '344','357',
            '49',
            '346','354','189','241','246','59','362','176',
            '137','152','61',
            '323','179','240','248',
            '50',
            '195',
            '366',
            '53',
            '351','262','279','51','185',
            '45','301','306','184',
            '4','11','19','25','302','47','82','343','353','115','121','128',
            '261','277','282','310','325','360','177','192','194','204','236','244','254',
            '5','12','20','26','79','116','122','129',
            '138',
            '55','249',
            '155',
            '316','322','198',
            '259','7','15','22','278','27','284','46','304','309','83','347','361','118','125','132','135','149','188','197','235','245','255',
            '258','6','13','21','283','29','312','321','80','117','123','131','187','202','238','253','300','363','174',
            '183','200','239',
            '60','350',
            '260','276','281','313','320','349','355','178','191','196','203','234','243','252',
            '54','314',
            '48','307','158',
            '8','14','18','28','299','78','144','124','130','134',
            '345','285','57','315','324','367','175','190','199','205','251',
            '181',
            '298','311','156',
            '256','263','305','56','317','326','348','358','365','136','151','237','247','180',
            '58',
            '182'
        ];
        Category::whereIn('id',$arr)->delete();
        echo "deleted";
        exit();

    }

    public function updateCategoryIdOfOtherAmenities()
    {
        //create offset and privacy categories with null property_id
        $cat_1 = Category::create([
            'category_name' => 'Offset',
            'global' => '0',
            'company_id' => '3',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        $cat_2 = Category::create([
            'category_name' => 'Privacy',
            'global' => '0',
            'company_id' => '4',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        //update all old offset and privacy category_id to new one
        $arr = [
            //offset
            $cat_1->id => ['3','10','17','24','81','113','120','127'],
            //privacy
            $cat_2->id => ['140','159']
        ];
        foreach($arr as $ak => $av){
            Amenity::whereIn('category_id',$av)->update(['category_id' => $ak]);
        }
        //soft delete old offset and privacy
        $del_arr = ['3','10','17','24','81','113','120','127','140','159'];
        Category::whereIn('id',$del_arr)->delete();
        echo "done";
        exit();
    }

    public function populateSlug()
    {
        $notices = Notice::all();
        foreach($notices as $nk => $nv){
            $slug = Notice::generateRandomSlug();
            $nv->slug = $slug;
            $nv->save();
        }
        echo "updated";
        exit();
    }

    public function populateEmailVerified()
    {
        User::withTrashed()->update(['email_verified_at'=> date('Y-m-d H:i:s')]);
        echo "updated";
        exit();
    }

    public function populatePropertyIdToCategories()
    {
        $categories = Category::where('global','0')->get();

        foreach($categories as $ck => $cv){
            $amenities = Amenity::where('category_id',$cv->id)->get(['id','amenity_name','category_id','property_id'])->toArray();
//            p($amenities);
            $arr = array();
            $m_arr = array();
            foreach($amenities as $ak => $av){
                if(!in_array($av['property_id'],$arr)){
                    $arr[] = $av['property_id'];
                    $m_arr[] = [
                        'aid' => $av['id'],
                        'pid' => $av['property_id'],
                        'cid' => $av['category_id']
                    ];
                }
            }
            if(count($m_arr) > 1){
                foreach($m_arr as $ark => $arv){
                    $cat = Category::withTrashed()
                        ->where('category_name',$cv->category_name)
                        ->where('property_id',$arv['pid'])
                        ->where('global','0')
                        ->first();
                    if($cat){
                        if($cat->trashed()){
                            $cat->deleted_at = NULL;
                            $cat->company_id = $cv->company_id;
                            $cat->save();
                        }
                        $cat_id = $cat->id;
                    }else{
                        $cat = Category::create([
                            'category_name' => $cv->category_name,
                            'global' => '0',
                            'company_id' => $cv->company_id,
                            'property_id' => $arv['pid'],
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                        $cat_id = $cat->id;
                    }
                    $amenity = Amenity::find($arv['aid']);
                    $amenity->category_id = $cat_id;
                    $amenity->save();
                }
                $cv->delete();
            }
//            p($arr);
//            p($m_arr);
//            echo " ================================================= ";

        }
        echo "done";
        exit();
    }

    public function formatCSV()
    {
        return Excel::download(new ExportFormattedCSV, 'sample.csv');
    }

    public function updateAmenityCategoryMapping()
    {
        $amenities = Amenity::with('property')
            ->get(['id','amenity_name','category_id','property_id']);
        foreach($amenities as $ak => $av){
            $acm = AmenityCategoryMapping::where('amenity_name',$av->amenity_name)
                ->where('company_id',$av->property->company_id)
                ->first();
            if($acm){
                AmenityCategoryMapping::firstOrCreate(
                    [
                        'amenity_name' => $acm->amenity_name,
                        'category_id' => $acm->category_id,
                        'company_id' => $acm->company_id,
                        'property_id' => $av->property_id,
                    ],
                    [
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]
                );
            }
        }
        AmenityCategoryMapping::whereNotNull('company_id')
            ->whereNull('property_id')
            ->delete();
        echo "updated";
        exit();
    }

    public function deleteExtraAmenities()
    {
        $count = 0;
//        $amenities = Amenity::with(['amenityValues','amenityValues.unitAmenityValues'])->whereNull('property_id')->get();
        $amenities = Amenity::whereNull('property_id')->forceDelete();
        echo "deleted";
        exit();
    }
    public function deleteSoftDeletedACM()
    {
        AmenityCategoryMapping::whereNotNull('deleted_at')->forceDelete();
        echo "deleted";
        exit();
    }

    public function updateMapDataForFloorPlan()
    {
        MappingTemplate::whereNull('property_id')->delete();
//        $res = FloorPlan::distinct('property_id')->get();
        $properties = Property::with(['mappingTemplates' => function($q){
            $q->where('table_name','floor_plans');
        },'floorPlans'])->get();
        foreach($properties as $pk=> $pv){
            if($pv->mappingTemplates->isEmpty() && !$pv->floorPlans->isEmpty()){
                $fp = $pv->floorPlans->first();
                $csv_header = ["PMS Property","Floorplan","Description","Beds","Baths","SqFt","Unit Count","Unit Type"];

                $row = [
                    ($fp->pms_property != '')?"pms_property":null,
                    ($fp->floor_plan != '')?"floor_plan":null,
                    ($fp->description != '')?"description":null,
                    ($fp->beds != '')?"beds":null,
                    ($fp->baths != '')?"baths":null,
                    ($fp->sqft != '')?"sqft":null,
                    ($fp->unit_count != '')?"unit_count":null,
                    ($fp->unit_type != '')?"unit_type":null,
                ];
                $mapping_template = MappingTemplate::create(
                    [
                        'table_name' => 'floor_plans',
                        'property_id' => $pv->id,
                        'template_name' => $pv->property_name.'-'.date('Y_m_d_H_i_s'),
                        'csv_header' => json_encode($csv_header),
                        'map_data' => json_encode($row),
                        'company_id' => $pv->company_id,
                        'saved' => 0,
                        'user_id' => \Auth::user()->id
                    ]
                );
            }
        }
        echo "updated";
        exit();
    }

    public function populateCompanyIdInAmenityCategoryMapping()
    {
        $acm = AmenityCategoryMapping::with(['property'])
            ->whereNotNull('property_id')->get();
        foreach($acm as $k => $v){
            $v->company_id = $v->property->company_id;
            $v->updated_at  = date('Y-m-d H:i:s');
            $v->save();
        }
        echo "updated";
        exit();
    }

    public function destroyEmptyAmenitiesOnACMAndAmenities()
    {
        AmenityCategoryMapping::where('amenity_name','')->forceDelete();
        Amenity::where('amenity_name','')->forceDelete();

        echo "deleted";
        exit();
    }

    public function listAmenityCategoryMapping()
    {
        $lists = DB::table('amenity_category_mapping')->get(['amenity_name','category_id','deleted_at'])->toArray();
        pe($lists);
    }

    public function updateFirstTime()
    {
        User::where('first_time','1')
            ->withTrashed()
            ->update(['first_time' => '0']);
        echo "updated";
        exit();
    }

    public function replaceFloorPlanOnMappingTemplate()
    {
        $mappingTemplates = MappingTemplate::where('table_name','floor_plans')
                ->get();
        $map_data = array();

        foreach($mappingTemplates as $mk => $mv){
            $map_data = json_decode($mv->map_data);
            $arr = array_map(
                function($str) {
                    $str = str_replace('floor_plan', 'pms_unit_type', $str);
                    if (trim($str) === ''){
                        $str = null;
                    }
                    return $str;
                },
                $map_data
            );
            $mv->map_data = json_encode($arr);
            $mv->save();
        }
        echo "updated";
        exit();
    }

    public function testExport()
    {
        $csv_file_path = storage_path('app/files/property/').'test_export.csv';
        if (!ini_get("auto_detect_line_endings")) {
            ini_set("auto_detect_line_endings", TRUE);
        }
        $csv = Reader::createFromPath($csv_file_path, 'r');

        $input_bom = $csv->getInputBOM();
        if ($input_bom === Reader::BOM_UTF16_LE || $input_bom === Reader::BOM_UTF16_BE) {
            $csv->addStreamFilter('convert.iconv.UTF-16/UTF-8');
        }
//        $csv->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');

//        $csv->setOutputBOM(Reader::BOM_UTF8);
//        $csv->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');

        $csv->setHeaderOffset(0);
        $csv_header = $csv->getHeader();

        $rec_arr = array();

        $stmt = (new Statement())
            ->offset(0)
            ->limit(100)
        ;

        $records = $stmt->process($csv);

        $records = $csv->getRecords(); //returns all the CSV records as an Iterator object
        pe($records);
        echo $csv->getContent(); //returns the CSV document as a string
    }
    public function removeCompanyForSuperAdmins()
    {
        User::where('role_id','1')->withTrashed()->update([
            'company_id'    => NULL,
            'company_role'  => NULL
        ]);
        echo "updated";
        exit();
    }

    public function removeReviewWithNullUnitAndMultipleUnits()
    {
        Review::whereNull('unit_id')
            ->whereNull('multiple_units')
            ->where('action','!=','5')
            ->delete();
        Review::whereNull('unit_id')
            ->where('multiple_units','[]')
            ->delete();
        echo "deleted";
        exit();
    }

    public function removeNullUnitAndBuildingOnAPR()
    {
        AmenityPricingReview::whereNull('unit_id')
                            ->orWhereNull('building_id')
                            ->orWhereNull('property_id')
                            ->withTrashed()
                            ->forceDelete();
        echo "deleted";
        exit();
    }

    public function removeNullBuildingOnUnits()
    {
        Unit::whereNull('building_id')->forceDelete();
        echo "deleted";
        exit();
    }
    public function removeOldWasherMapping()
    {
        $arr = ['w/d','wd','washer','dryer'];
        foreach($arr as $ak => $av){
            $ams = AmenityCategoryMapping::where('amenity_name',$av)
                ->get();
            foreach($ams as $avk => $avv){
                if($avk != 0){
                    $avv->forceDelete();
                }
            }
        }
        echo "deleted";
        exit();
    }
    public function trimAmenityOnAmenityCategoryMapping()
    {
        DB::statement("UPDATE `amenity_category_mapping` SET `amenity_name` = TRIM(`amenity_name`)");
        DB::statement("UPDATE `amenities` SET `amenity_name` = TRIM(`amenity_name`)");
        DB::statement("UPDATE `categories` SET `category_name` = TRIM(`category_name`)");

        echo "updated";
        exit();
    }

    public function removeOrphanNotice()
    {
        Notice::whereNull('property_id')
            ->orWhere('file_type')
            ->delete();
        echo "deleted";
        exit();
    }
    public function forceDeleteUsers()
    {
        User::onlyTrashed()->forceDelete();
        echo "deleted";
        exit();
    }
    public function fixFloor(){
        $units_arr = ['47805','47806','47807','47808','47809','47810','47811','47812','47813','47814','47815','47816','47817','47818','47819','47821','47822','47823','47824','47825','47946','47947','47948','47951','47953','47954','47955','47956','47957','47958','47959','47960','47961','47962','47963','47964','47965','47966','47967','47968','47969','47970','47971','47972','47973','47974','47975','47976','47977','47978','47979','47980','47981','47982'];

        foreach($units_arr as $v){
            UnitAmenityValue::updateOrCreate(
                [
                    'amenity_value_id' => 6745,
                    'unit_id' => $v
                ],
                [
                    'uav_status' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            );
        }
        echo "fixed";
        exit();
    }

}
