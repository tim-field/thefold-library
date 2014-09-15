<?php

namespace TheFold\Wordpress;

class Export
{

    /**
     * Example maping, export field to a way to get the export field
     *
     * $map = [
     *
     *    'solr_id' => function($post){
     *        return $this->get_solr_id($post->ID,'WP_Post');
     *    },
     *    'blogid' => function($post){
     *        return get_current_blog_id();
     *    },
     *    'id' => 'ID',
     *    'post_author' => 'post_author', //This is the users id
     *    'post_name' => 'post_name',
     * ]
     */
    static function export_object($object, $map, $format='array'){

        $export = $format == 'array' ? [] : new $format;
        
        $have_switched = false;

        if(is_multisite() && $object instanceof \WP_Post && $object->blogid != get_current_blog_id()){
            switch_to_blog($object->blogid); 
            $have_switched = true;
        }

        foreach ($map as $export_field => $wp_field) {

            $value = null;

            if (is_string($wp_field)) {
                $value = is_array($object) ? $object[$wp_field] : $object->$wp_field;
            }
            elseif ($wp_field instanceof \Closure){
                $value = $wp_field($object);
            }

            if($format == 'array'){
                $export[$export_field] = $value;
            }else{
                $export->$export_field = $value;
            }
        }

        if($have_switched){
            restore_current_blog();
        }

        return $export;
    }
}
