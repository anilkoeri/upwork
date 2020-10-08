<?php


namespace App\Exports;

use App\Imports\FormatCSV;
use Maatwebsite\Excel\Concerns\FromArray;
use Excel;


class ExportFormattedCSV implements FromArray
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }
    public function array(): array
    {
        $path = storage_path('app/files/scrubber/') . $this->data['file_name'];
        $arr = Excel::toArray(new FormatCSV(), $path);
        $arr = array_shift($arr);

        $arr = array_slice($arr, 7);
        array_pop($arr);
        array_pop($arr);

        $rec_arr = $empty_col = array();
        $empty_col_checked = false;
        foreach ($arr as $ak=>$av){
                if(count(array_filter($av)) != 0){
                    if(!$empty_col_checked){
                        foreach($av as $k => $v){
                            if($v == ''){
                                $empty_col[] = $k;
                            }
                        }
                        $empty_col_checked = true;
                    }
                    $rec_arr[] = $av;
                }
        }

        
        
        

        foreach($empty_col as $ek => $ev){
            if(empty( array_filter(array_column($rec_arr,$ev))) )
            {
                foreach($rec_arr as &$item) {
                    unset($item[$ev]);
                }
                unset($item);
            }
        }
        
        $pre_val = '';
        $format_header = true;
        foreach ($rec_arr as $ak => $av) {
            foreach ($av as $k => $v) {
                if ($v == '' && $k == 0) {
                    $rec_arr[$ak][$k] = $pre_val;
                } elseif ($k == 0){
                    $pre_val = $v;
                }
                if($format_header){
                    $rec_arr[$ak][$k] = trim(preg_replace('/\s+/', ' ', $v));;
                }
            }
            $format_header = false;
        }

        return $rec_arr;
    }

}
