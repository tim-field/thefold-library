<?php

namespace TheFold\WordPress\Admin\Field;

class PostMeta extends \TheFold\WordPress\Admin\Field{

    function get_value($post_id){

        $field = $this->name;
        $property = null;
        
        //convert geodata.lat to $geodata['lat']
        if($pos = strpos($field,'.')){

            $property = substr($field,$pos+1);
            $field = substr($field,0,$pos);
        }
        
        $value = get_post_meta($post_id,$field,true);

        if($property && isset($value[$property])){
            return $value[$property]; 
        }

        return $value;
    }
    
    function filter_args($args,$condition,$value){

        $args['meta_query'][] = [
                'key' => $this->name,
                'compare' => $condition,
                'value' => $value,
                'type' => $this->type
        ];

        return $args;
    }
}
