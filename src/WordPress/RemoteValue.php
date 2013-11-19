<?php
namespace TheFold\WordPress;

/**
 * Pull data from a remove value and store it in the meta tables 
 */

abstract class RemoteValue
{
    //the data we are interested in from the remote src
    abstract static function get_meta_keys();
    //how we get that data ( the api call ), this needs to return as an associative array
    abstract static protected function get_data($id);
    //return a namespace for the meta keys 
    abstract static function get_meta_prefix();

    public static function get_value( $field, $id = null, $force_update=false)
    {
        if(!$id) $id = \get_the_ID();

        if($force_update || !$value = static::get_local( $id, $field )) {

            $data = static::set_local( $id, static::get_data($id) );

            if(!isset($data[$field]))
                throw new \Exception('Unknown remote field'.$field);

            $value = $data[$field];
        }

        return $value;
    }

    protected static function set_local( $id, $data)
    {
        foreach( static::get_meta_keys() as $field ){

            if(!isset($data[$field]))
                \trigger_error('Can\'t find '.$field.' in returned data',\E_USER_WARNING);

            \update_metadata(
                static::get_domain(), 
                $id, 
                static::prefix_meta($field), 
                $data[$field]
            );
        }

        return $data; 
    }

    protected static function get_local($id,$field)
    {
        if( in_array( $field, static::get_meta_keys()) )

            return \get_metadata(
                static::get_domain(), 
                $id, 
                static::prefix_meta($field),
                true
            );

        return null;
    }
    
    /*
     * The meta tables that we will store info.
     *
     * When I coded all of this I thought WP would let me use any domain when using the update_metadata and get_metadata functions
     * then after writing all this found that it's restricted to post, user & comment; at least for now.
     *
     */
    static function get_domain()
    {
        return 'post';
    }

    static function prefix_meta($field)
    {
        return static::get_meta_prefix().$field;
    }
}
