<?php
namespace TheFold\Wordpress;

/**
 *     $ID = Import::import_post(
       Import::map_data(
            array(
                'ID' => 'post_id',
                'post_title' => 'name',
                'post_content' => 'description',
                'post_date' => function($data){
                    return date('Y-m-d H:i:s',strtotime($data['start_time']));
                },
                'post_date_gmt' => function($data){
                    return gmdate('Y-m-d H:i:s',strtotime($data['start_time']));
                },
                'latitude' => function($data){ return $data['venue']['latitude']; },
                'longitude' =>function($data){ return  $data['venue']['longitude']; },
                'city' => function($data){ return  $data['venue']['city']; },
                'venue_id' => function($data){ return  $data['venue_id']; },

                'attending_count' => 'attending_count',
                'unsure_count' => 'unsure_count',
                
                'fb_id' => 'id',
            ),
            $detail
       ),
       Events\EVENT_CPT,
       'publish'
   );
 */

class Import
{
    static function import_post($post_data, $type='post', $status=null)
    {
        Wordpress::log($post_data);

        $core['post_type'] = $type;
        $core['post_status'] = $status;

        $core_fields = static::core_fields();

        foreach ($core_fields as $core_field){

            if(isset($post_data[$core_field])){
                $core[$core_field] = $post_data[$core_field];
                unset($post_data[$core_field]);
            }
        }

        if (!isset($core['ID']))
        {
            global $wpdb;

            $ID = $wpdb->get_var( $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = %s",$core['post_name'], $core['post_type']) );

            if(is_wp_error($ID))
                throw new \Exception($ID);

            $core['ID'] = $ID;
        }


        if (!$ID = wp_insert_post($core))
            throw new \Exception('Unable to insert post');

        foreach ($post_data as $key => $value) {
            update_post_meta($ID, $key, $value );
        }

        return $ID;
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
            elseif(!is_string($data_field) && is_callable($data_field)){
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

    static function core_fields(){
        return array(
            'ID',
            'menu_order',
            'comment_status', 
            'ping_status',
            'pinged',
            'post_author',
            'post_category',
            'post_content',
            'post_date',
            'post_date_gmt',
            'post_excerpt',
            'post_name',
            'post_parent',
            'post_password',
            'post_status',
            'post_title',
            'post_type',
            'tags_input',
            'to_ping',
            'tax_input',
        );
    }

    function create_attachement($path, $basename=null){

        if(empty($path)) return null;

        if(!$basename)
        $basename = basename($path);

        $wp_upload_dir = wp_upload_dir(); 

        $path = str_replace(WP_CONTENT_URL,WP_CONTENT_DIR,$path);

        $file = $wp_upload_dir['path'].'/'.$basename;
        $wp_filetype = wp_check_filetype($basename, null );

        copy($path,$file);

        $attachment_id = wp_insert_attachment(array(
            'guid' => $wp_upload_dir['baseurl'] .'/'. _wp_relative_upload_path( $file ), 
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', $basename),
            'post_content' => '',
            'post_status' => 'inherit'
        ), $file, $parent = 0);

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata( $attachment_id, $file );
        wp_update_attachment_metadata( $attachment_id, $attachment_data );

        return $attachment_id;
    }
}
