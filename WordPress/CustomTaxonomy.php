<?php

namespace TheFold\WordPress;

class CustomTaxonomy extends QuickConfig
{
    public $object_type;

    function __construct($type, $name, $object_type=null, $info=array()){
        
        parent::__construct($type,$name,$info); 

        $this->object_type = $object_type;

        $this->register_taxonomy();
    }

    protected function register_taxonomy()
    {
        $me = $this; //until php 5.4

        \add_action( 'init', function() use ($me) {


            /**
             * Properties of $me are pulled from the $info array passed
             * to constructor. If they don't exist we look for a default_{property} method
             */
            
            $plural = $me->plural;

            register_taxonomy($me->type, $me->object_type, array( 
                // Hierarchical taxonomy (like categories)
                'hierarchical' => $me->hierarchical,
                'labels' => array(
                    'name' => _x( $plural, 'taxonomy general name' ),
                    'singular_name' => _x( $me->name, 'taxonomy singular name' ),
                    'search_items' =>  __( 'Search '.$plural ),
                    'all_items' => __( 'All '.$plural ),
                    'parent_item' => __( 'Parent '.$me->name ),
                    'parent_item_colon' => __( 'Parent '.$me->name ),
                    'edit_item' => __( 'Edit '.$me->name ),
                    'update_item' => __( 'Update '.$me->name ),
                    'add_new_item' => __( 'Add New '.$me->name ),
                    'new_item_name' => __( 'New '.$me->name ),
                    'menu_name' => __( $plural ),
                ),
                // Control the slugs used for this taxonomy
                'rewrite' => array(
                    'slug' => $me->slug, // This controls the base slug that will display before each term
                    'with_front' => $me->rewrite_with_front, // Don't display the category base before "/locations/"
                    'hierarchical' => $me->rewrite_hierarchical// This will allow URL's like "/locations/boston/cambridge/"
                ),
            )); 
               
        },9999);
    }
 
    protected function default_rewrite_with_front() {
        return false;
    }
    
    protected function default_rewrite_hierarchical() {
        return true;
    }
}
