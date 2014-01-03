<?php
namespace TheFold\Wordpress;

trait Cache {

    function cache_get($key, $group=null){

        if(!$group){
            $group = __CLASS__;
        }

        return wp_cache_get($key,$group) ?: null;
    }

    function cache_set($key, $data, $group=null)
    {
        if(!$group){
            $group = __CLASS__;
        }
        
        wp_cache_set($key, $data, $group);

        return $data;
    }
}
