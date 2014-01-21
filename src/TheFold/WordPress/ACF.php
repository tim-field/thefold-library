<?php
namespace TheFold\WordPress;

class ACF{

    use \TheFold\Singleton;

    //Always pass post_id if you can. Otherwise if two fields have the same name the result will be unpredicatable.
    public function get_field_type($field_name, $post_id=null)
    {
        static $type_cache;

        $k = $field_name.':'.$post_id;

        if(!isset($type_cache[$k])){
            $field_object = $this->get_field_object($field_name, $post_id, ['load_value'=>false]);
            $type_cache[$k] = $field_object['type'];
        }

        return $type_cache[$k];
    }

    public function get_field_object($field_name, $post_id=null, $options)
    {
        $field_key = $this->get_field_key($field_name, $post_id);

        return get_field_object($field_key, $post_id, $options);
    }

    public function get_field_key($field_name, $post_id=null)
    {
        if($post_id){
        
	    return get_field_reference( $field_name, $post_id );
        }

        global $wpdb;

        //Default to first in meta table with this name... Best we can do without a post_id

        $safe_field_name = preg_replace('/[^\w\s]/','',$field_name);

        return $wpdb->get_var("SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = '_$field_name'");
    }

    //Note, this only works if the ACF fields are defined in the admin.
    //Better to use get_field_object
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
