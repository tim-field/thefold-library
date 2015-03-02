<?php

namespace TheFold\WordPress\Admin\Field;


class PostTerm extends \TheFold\WordPress\Admin\Field{

    function get_value($post_id){
        return wp_get_post_terms( $post_id, $this->name, ['fields'=>'name']);
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
