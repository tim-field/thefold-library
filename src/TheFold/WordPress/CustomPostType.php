<?php
namespace TheFold\WordPress;

/**
 * Setup Custom Post Types 
 */
class CustomPostType extends QuickConfig
{
    //https://developer.wordpress.org/resource/dashicons/
    function __construct($type, $name, $info=array()){

        if(strlen($type) > 20){
            throw new \Exception('CPT type names have a max lenght of 20 chars');
        }

        parent::__construct($type,$name,$info); 

        $this->setup_post_type();
    }

    protected function default_menu_icon(){
        return null;
    }

    protected function default_taxonomies(){
        return array();
    }

    protected function default_public(){
        return true;
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
        add_action( 'init', function() {
            
            /**
             * Properties of $me are pulled from the $info array passed
             * to constructor. If they don't exist we look for a default_{property} method
             */
            
            $plural = $this->plural;
            
            register_post_type( $this->type, [
                'label' => $plural,
                'labels' => [
                    'name' => $plural,
                    'singular_name' => $this->name,
                    'add_new_item' => 'Add New '.$this->name,
                    'edit_item' => 'Edit '.$this->name,
                    'new_item' => 'New '.$this->name,
                    'view_item' => 'View '.$this->name,
                    'search_items' => 'Search '.$plural,
                    'parent_item_colon' => 'Parent '.$this->name
                ],
                'public' => $this->public,
                'rewrite' => array('slug' => $this->slug, 'with_front' => $this->rewrite_with_front ),
                'menu_position' => $this->menu_position,
                'menu_icon' => $this->menu_icon,
                'hierarchical' => $this->hierarchical,
                'supports'=> $this->supports,
                'taxonomies' => $this->taxonomies,
                'has_archive' => $this->has_archive
            ]);

            /*
            add_filter( 'post_updated_messages', function(){

                $post             = get_post();
                $post_type        = get_post_type( $post );
                $post_type_object = get_post_type_object( $post_type );

                $messages[$this->type] = array(
                    0  => '', // Unused. Messages start at index 1.
                    1  => "$this->name updated.",
                    2  => "Custom field updated.",
                    3  => "Custom field deleted.",
                    4  => "$this->name updated.",
                    // translators: %s: date and time of the revision 
                    5  => isset( $_GET['revision'] ) ? sprintf( $this->name ." restored to revision from %s" , wp_post_revision_title( (int) $_GET['revision'], false ) )  : false,
                    6  => $this->name.' published',
                    7  => $this->name. ' saved',
                    8  => $this->name.' submitted',
                    9  => sprintf(
                        $this->name. ' scheduled for: <strong>%1$s</strong>',
                        // translators: Publish box date format, see http://php.net/date
                        date_i18n('M j, Y @ G:i'), strtotime( $post->post_date )
                    ),
                    10 => $this->name.' draft updated.'
                );

                if ( $post_type_object->publicly_queryable ) {
                    $permalink = get_permalink( $post->ID );

                    $view_link = sprintf( ' <a href="%s">%s</a>', esc_url( $permalink ),  'View '.$this->name  );
                    $messages[ $post_type ][1] .= $view_link;
                    $messages[ $post_type ][6] .= $view_link;
                    $messages[ $post_type ][9] .= $view_link;

                    $preview_permalink = add_query_arg( 'preview', 'true', $permalink );
                    $preview_link = sprintf( ' <a target="_blank" href="%s">%s</a>', esc_url( $preview_permalink ), 'Preview '.$this->name );
                    $messages[ $post_type ][8]  .= $preview_link;
                    $messages[ $post_type ][10] .= $preview_link;
                }

                return $messages;
            });*/

        },99); // why 99 ?
    }
}

