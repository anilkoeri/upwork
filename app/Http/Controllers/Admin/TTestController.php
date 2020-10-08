<?php

namespace App\Http\Controllers\Admin;

use App\Models\TTest;
use App\Http\Controllers\Controller;
use MathPHP\Statistics\Significance;
use DB;

$GLOBALS = array(
        /* for calculating t-test's critical value */
    'critval' => array(0,6.3138,2.92,2.3534,2.1319,2.015,1.9432,1.8946,1.8595,1.8331,1.8124,1.7959,1.7823,1.7709,1.7613,1.753,1.7459,1.7396,1.7341,1.7291,1.7247,1.7207,1.7172,1.7139,1.7109,1.7081,1.7056,1.7033,1.7011,1.6991,1.6973,1.6955,1.6939,1.6924,1.6909,1.6896,1.6883,1.6871,1.6859,1.6849,1.6839,1.6829,1.682,1.6811,1.6802,1.6794,1.6787,1.6779,1.6772,1.6766,1.6759,1.6753,1.6747,1.6741,1.6736,1.673,1.6725,1.672,1.6715,1.6711,1.6706,1.6702,1.6698,1.6694,1.669,1.6686,1.6683,1.6679,1.6676,1.6673,1.6669,1.6666,1.6663,1.666,1.6657,1.6654,1.6652,1.6649,1.6646,1.6644,1.6641,1.6639,1.6636,1.6634,1.6632,1.663,1.6628,1.6626,1.6623,1.6622,1.662,1.6618,1.6616,1.6614,1.6612,1.661,1.6609,1.6607,1.6606,1.6604,1.6602,1.6601,1.6599,1.6598,1.6596,1.6595,1.6593,1.6592,1.6591,1.6589,1.6588,1.6587,1.6586,1.6585,1.6583,1.6582,1.6581,1.658,1.6579,1.6578,1.6577,1.6575,1.6574,1.6573,1.6572,1.6571,1.657,1.657,1.6568,1.6568,1.6567,1.6566,1.6565,1.6564,1.6563,1.6562,1.6561,1.6561,1.656,1.6559,1.6558,1.6557,1.6557,1.6556,1.6555,1.6554,1.6554,1.6553,1.6552,1.6551,1.6551,1.655,1.6549,1.6549,1.6548,1.6547,1.6547,1.6546,1.6546,1.6545,1.6544,1.6544,1.6543,1.6543,1.6542,1.6542,1.6541,1.654,1.654,1.6539,1.6539,1.6538,1.6537,1.6537,1.6537,1.6536,1.6536,1.6535,1.6535,1.6534,1.6534,1.6533,1.6533,1.6532,1.6532,1.6531,1.6531,1.6531,1.653,1.6529,1.6529,1.6529,1.6528,1.6528,1.6528,1.6527,1.6527,1.6526,1.6526,1.6525,1.6525)
);

class TTestController extends Controller
{
    protected $glob;
    public function __construct()
    {
        global $GLOBALS;
        $this->glob = &$GLOBALS;
    }

    public function index()
    {
        $allData = TTest::get();
        $cohort_1 = $cohort_2 = array();
        foreach($allData as $sData){
            $cohort_1[] = ($sData->cohort_1) ? $sData->cohort_1 : 0;
            $cohort_2[] = ($sData->cohort_2) ? $sData->cohort_2 : 0;
        }
        echo "<pre>";
        $tTest = Significance::tTest($cohort_1, $cohort_2);
        print_r($tTest);
        //dd($this->unpairedttest($cohort_1, $cohort_2));
        
    //     echo "stats_stat_independent_t - ".stats_stat_independent_t($cohort_1, $cohort_2)."<br>";
    //     echo "stats_stat_paired_t - " . stats_stat_paired_t($cohort_1, $cohort_2);
        exit;
    //     return view('admin.ttest.index',compact('property'));
    }


/* -------------------------------------------- */
/* ------- sd_square -------------------------- */
/* -------------------------------------------- */
// Function to calculate square of value - mean
public function sd_square($x, $mean) { return pow($x - $mean,2); }


/* -------------------------------------------- */
/* ------- sd --------------------------------- */
/* -------------------------------------------- */
// Function to calculate standard deviation (uses sd_square)    
public function sd($array) {
    // square root of sum of squares devided by N-1
    return sqrt(array_sum(array_map(array($this, 'sd_square'), $array, array_fill(0,count($array), (array_sum($array) / count($array)) ) ) ) / (count($array)-1) );
}


/* -------------------------------------------- */
/* ------- unpairedttest ---------------------- */
/* -------------------------------------------- */
private function unpairedttest($data1,$data2) {
    if ((count($data1) < 2) || (count($data2) < 2)) {
        return array(0,0,0,0,0);
    }

    /* compute stats about dataset1 */
    $mean1 = array_sum($data1)/count($data1);
    $sd1 = $this->sd($data1);
    $variance1 = $sd1*$sd1;
    $n1 = count($data1);
    $stderr1 = $variance1/$n1;

    /* compute stats about dataset2 */
    $mean2 = array_sum($data2)/count($data2);
    $sd2 = $this->sd($data2);
    $variance2 = $sd2*$sd2;
    $n2 = count($data2);
    $stderr2 = $variance2/$n2;

    $stderr = sqrt($stderr1 + $stderr2);
    $meandiff = abs($mean1-$mean2);
    if ($stderr > 0) {
        $tvalue = $meandiff/$stderr;
    }
    else {
        $tvalue = 0;
    }
    $df = $n1 + $n2 -2;
    if ($df > 100) {
        $criticaltvalue = $this->glob['critval'][100];
    }
    else {
        $criticaltvalue = $this->glob['critval'][$df];
    }

    if ($tvalue > $criticaltvalue) {
        /* they are statistical different */
        $statisticallydifferent = 1;
    }
    else {
        /* they ain't */
        $statisticallydifferent = 0;
    }

    return array($statisticallydifferent,$stderr,$meandiff,$tvalue,$df);
}

    
}


