<?php

namespace TheFold\WordPress\Admin\Field;

class Post extends \TheFold\WordPress\Admin\Field{

    function get_value($post_id){
        $post = get_post($post_id);
        
        return $post->{$this->name};
    }
    
    function filter(\WP_Query &$WP_Query, $condition, $value)
    {
        global $wpdb;

        $value = $this->type === 'DATE' ? date('Y-m-d H:i:s',strtotime($value)) : $value;

        if(in_array($condition, ['LIKE','NOT LIKE'])){
            $where = " AND `$this->name` $condition '%" . like_escape($value) . "%' ";
        }
        else{
            $where = $wpdb->prepare(" AND `$this->name` $condition %s ", $value);
        }

        $WP_Query->query_from .= $where;
    }
}
