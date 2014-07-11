<?php
/**
 * This was used as a base.
 * https://github.com/crowdfavorite-mirrors/wp-duplicate-post/blob/master/duplicate-post-admin.php
 */

namespace TheFold\WordPress;

use TheFold\WordPress\Import;

class DuplicatePost 
{
    const META_ORIGINAL = '_dp_original';
    const META_DUPLICATE = '_dp_duplicate';

    function duplicate(\WP_Post $post, $mapping=[], $parent_id = null) {

        // We don't want to clone revisions
        if ($post->post_type == 'revision') return;

        $duplicate_map = apply_filters('thefold_wordpress_duplicate_mapping',
            array_merge(
                $this->get_dupplicate_map_defaults($post, $parent_id),
                $mapping
            ),
            $post,
            $mapping,
            $parent_id
        );

        $post_data = Import::map_data($duplicate_map, (array) $post);

        $new_post_id = Import::import_post( $post_data, $post_data['post_type'] );

        delete_post_meta($new_post_id, self::META_ORIGINAL);
        add_post_meta($new_post_id, self::META_ORIGINAL, $post->ID);
        
        delete_post_meta($post->ID, self::META_DUPLICATE);
        add_post_meta($post->ID, self::META_DUPLICATE, $new_post_id);

        //copy children
        $children = get_posts([ 
            'post_type' => 'any', 
            'numberposts' => -1, 
            'post_status' => 'any', 
            'post_parent' => $post->ID]
        );

        foreach($children as $child){
        
            $this->duplicate($child, $mapping, $new_post_id);
        }

        return $new_post_id;
    }

    function get_dupplicate_map_defaults(\WP_Post $post, $parent_id){
        
        //start with all core field names
        $defaults = array_combine(
            Import::core_fields(), 
            Import::core_fields()
        );
    
        //force a new post
        unset($defaults['ID']);

        //with a new slug
        unset($defaults['post_name']);

        //default to current user as post author
        $defaults['post_author'] = function($post_data) {
            return get_current_user_id();
        };

        $defaults['post_category'] = function($post_data) use ($post){

            return wp_get_post_categories( $post->ID ); 
        };

        $defaults['tags_input'] = function($post_data) use ($post) {

            return wp_get_post_tags( $post->ID, ['fields'=>'names'] );
        };

        $defaults['tax_input'] = function($post_data) use ($post) {

            foreach(get_object_taxonomies($post->post_type) as $taxonomy){

                if($taxonomy != 'post_category' && $taxonomy != 'post_tag'){

                    $tax_input[$taxonomy] = wp_get_object_terms($post->ID, $taxonomy,['fields'=>'ids']);
                }
            }
        };

        //post meta
        foreach(get_post_custom_keys($post->ID) as $meta_key)
        {
            $defaults[$meta_key] = function($post_data) use ($post, $meta_key) {

                return get_post_meta( $post->ID, $meta_key, true );
            };
        }

        if($parent_id) 
        {
            $defaults['parent_id'] = function($post_data) use ($parent_id){
                return $parent_id;
            };
        }

        return $defaults;
    }



}
