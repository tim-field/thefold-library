<?php
namespace TheFold\WordPress;

/**
 * Setup Custom Post Types 
 */
class CustomPostType extends QuickConfig
{
    function __construct($type, $name, $info=array()){

        parent::__construct($type,$name,$info); 

        $this->setup_post_type();
    }

    protected function default_menu_icon(){
        return null;
    }

    protected function default_taxonomies(){
        return array();
    }

    protected function default_supports(){
        return array(
                'title',
                'editor',
                'thumbnail',
                'excerpt',
                'revisions',
                'page-attributes'
            );
    }

    protected function default_rewrite_with_front() {
        return true;
    }

    protected function setup_post_type()
    {
        $me = $this; //until php 5.4

        \add_action( 'init', function() use ($me) {

            
            /**
             * Properties of $me are pulled from the $info array passed
             * to constructor. If they don't exist we look for a default_{property} method
             */
            
            $plural = $me->plural;
            
            \register_post_type( $me->type, array(
                'label' => $plural,
                'labels' => array( 
                    'name' => $plural,
                    'singular_name' => $me->name,
                    'add_new_item' => 'Add New '.$me->name,
                    'edit_item' => 'Edit '.$me->name,
                    'new_item' => 'New '.$me->name,
                    'view_item' => 'View '.$me->name,
                    'search_items' => 'Search '.$plural,
                    'parent_item_colon' => 'Parent '.$me->name
                ),
                'public' => true,
                'rewrite' => array('slug' => $me->slug, 'with_front' => $me->rewrite_with_front ),
                'menu_position' => $me->menu_position,
                'menu_icon' => $me->menu_icon,
                'hierarchical' => $me->hierarchical,
                'supports'=> $me->supports,
                'taxonomies' => $me->taxonomies
            ));

        },9999);
    }
}

