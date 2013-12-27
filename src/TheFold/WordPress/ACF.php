<?php
namespace TheFold\WordPress;

class ACF{

    public static function get_field_meta($field_name)
    {
        static $meta_cache;

        if(!isset($meta_cache[$field_name])){

            global $wpdb;

            $safe_field_name = preg_replace('/[^\w\s]/','',$field_name);

            $serialized_meta = $wpdb->get_var( "
                SELECT acf_meta_value.meta_value 
                FROM $wpdb->postmeta AS post_meta
                JOIN $wpdb->postmeta AS acf_meta_key ON acf_meta_key.meta_key = '_$safe_field_name'
                JOIN $wpdb->postmeta AS acf_meta_value ON acf_meta_value.meta_key = acf_meta_key.meta_value
                WHERE post_meta.meta_key = '$safe_field_name'
                LIMIT 1"
            );

            if($serialized_meta){
                $meta_cache[$field_name] = unserialize($serialized_meta);
            } else {
                $meta_cache[$field_name] = false;
            }
        }

        return $meta_cache[$field_name] ?: null; 
    }

}
