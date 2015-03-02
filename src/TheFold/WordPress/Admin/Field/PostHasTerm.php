<?php

namespace TheFold\WordPress\Admin\Field;

class PostHasTerm extends \TheFold\WordPress\Admin\Field {
    
    function get_value($post_id){

        return has_term($this->label,$this->name,$post_id) ? 'Y' : 'N';
    }
    
    function filter_args($args, $condition, $value){

        $args['tax_query'][] = [
            'taxonomy' => $this->name,
            'field' => 'slug',
            'terms' => $value,
            'operator' => $condition == '!=' ? 'NOT IN' : 'IN'
        ];

        return $args;
    }
}
