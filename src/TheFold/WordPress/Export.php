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

        foreach ($map as $export_field => $wp_field) {

            $value = null;

            if (is_string($wp_field)) {
                $value = $object->$wp_field;
            }
            elseif (is_callable($wp_field)){
                $value = $wp_field($object, $blog_id);
            }

            if($format == 'array'){
                $export[$export_field] = $value;
            }else{
                $export->$export_field = $value;
            }
        }

        return $export;
    }
}
