<?php
namespace TheFold;

use TheFold\KLogger;

class Log {

    static function add($message,$level=KLogger::DEBUG,$dir=null)
    {
        if(!$dir){
            $dir = self::get_dir();
        }
    
        KLogger::instance($dir, $level)->Log( is_string($message) ? $message : print_r($message,true), $level);
    }

    static function get_dir() {
        return realpath($_SERVER['DOCUMENT_ROOT'].'/../logs/');
    }
}
