<?php


if(!function_exists('tryCatchError')){
    function tryCatchError($func){
        try{
            $func();
        }catch(\Exception $err){
            return response()->json(["success" => false , "eroor" => $err]);
        }
    }
}