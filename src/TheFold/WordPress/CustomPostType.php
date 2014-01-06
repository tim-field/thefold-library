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
        \add_action( 'init', function() {
            
            /**
             * Properties of $me are pulled from the $info array passed
             * to constructor. If they don't exist we look for a default_{property} method
             */
            
            $plural = $this->plural;
            
            \register_post_type( $this->type, array(
                'label' => $plural,
                'labels' => array( 
                    'name' => $plural,
                    'singular_name' => $this->name,
                    'add_new_item' => 'Add New '.$this->name,
                    'edit_item' => 'Edit '.$this->name,
                    'new_item' => 'New '.$this->name,
                    'view_item' => 'View '.$this->name,
                    'search_items' => 'Search '.$plural,
                    'parent_item_colon' => 'Parent '.$this->name
                ),
                'public' => true,
                'rewrite' => array('slug' => $this->slug, 'with_front' => $this->rewrite_with_front ),
                'menu_position' => $this->menu_position,
                'menu_icon' => $this->menu_icon,
                'hierarchical' => $this->hierarchical,
                'supports'=> $this->supports,
                'taxonomies' => $this->taxonomies
            ));

        },99);
    }
}

