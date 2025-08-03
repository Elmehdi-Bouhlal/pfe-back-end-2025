<?php


if(!function_exists('tryCatchError')){
    function tryCatchError(callable $func){
        try{
            $func();
        }catch(\Exception $err){
            dd($err);
        }
    }
}