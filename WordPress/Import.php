<?php
namespace TheFold\WordPress;

class Import
{
    static function import_post($post_data, $type='post', $status=null)
    {
        $core['post_type'] = $type;
        $core['post_status'] = $status;

        foreach(array('post_title','post_name','post_content','post_status','post_excerpt','ID','post_author') as $core_field){

            if(isset($post_data[$core_field])){
                $core[$core_field] = $post_data[$core_field];
                unset($post_data[$core_field]);
            }
        }

        if(!isset($core['ID']))
        {
            global $wpdb;

            $ID = $wpdb->get_var( $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = %s",$core['post_name'], $core['post_type']) );

            if(is_wp_error($ID))
                throw new Exception($ID);

            $core['ID'] = $ID;
        }


        if(!$ID = wp_insert_post($core))
            throw new Exception('Unable to insert post');

        foreach($post_data as $key => $value){
            update_post_meta($ID, $key, $value );
        }

        echo $core['post_name']."\n";

    }

    /**
     *
     * Pass in an array that maps wp_field names to field names in the raw data
     * Example.
     *
     * array(
            'post_title' => 'name',
            'post_name' =>  'identifier',
            'post_content'=> 'blurb',
            'year' => function($data){
                return substr($data['date_start'],0,4);
            },
            'intergration_id' => 'id'
        )
    *
    * @param $field_map is an associative array of wp_fields to import fields
    * @param $data raw data that will be used to mapped to wp_fields ready to pass to import_post 
    * @return associative array ready for WP to use @see import_post
    *
    * */
    static function map_data($field_map, $data) 
    {

        $post_data = array();

        foreach( $field_map as $wp_field => $data_field ){

            if(is_array($data_field)) {

                $values = array_reduce($data_field, function($values, $field) use ($data) {

                    $values[] = $data[$field];

                    return $values;
                });

                $value = implode(' ',array_filter($values));
            }
            elseif(is_callable($data_field)){
                $value = $data_field($data);
            }
            else 
                $value =  $data[$data_field];


            $post_data[$wp_field] = $value;
        }

        return $post_data;
    }

    static function connect_posts($type, $from_id, $to_id, $data=array())
    {
        p2p_type( $type )->connect( $from_id, $to_id, $data);
    }
}
