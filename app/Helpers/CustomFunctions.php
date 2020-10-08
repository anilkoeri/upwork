<?php

namespace App\Helpers;


class CustomFunctions
{
    public static function softEmpty($var) {
        pe($var);
        if( $var==="0" || $var ){
            return false;
        }else{
            return true;
        }
    }
}

