<?php


namespace App\Helpers;

use Carbon\Carbon;


class SanitizeData
{
    public static function formatNullAndEmpty($data)
    {
        return (!empty($data) && strtolower($data) != 'null' ? trim($data) : NULL);
    }

    public static function formatNull($data)
    {
        return ( (strtolower($data) != 'null' && trim($data) != '' ) ? trim($data) : NULL);
    }

    public static function formatDate($date)
    {
        $date = self::formatNullAndEmpty($date);
        if(strtotime($date) === false){
            return NULL;
        }else{
            return Carbon::parse($date)->format('Y-m-d');
        }
    }

    public static function substr_with_ellipsis($string, $chars = 10)
    {
        preg_match('/^.{0,' . $chars. '}(?:.*?)\b/iu', $string, $matches);
        $new_string = $matches[0];
        return ($new_string === $string) ? $string : $new_string . '...';
    }

}
