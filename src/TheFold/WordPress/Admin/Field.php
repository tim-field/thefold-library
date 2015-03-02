<?php

namespace TheFold\WordPress\Admin;

abstract class Field{

    protected $name;
    protected $label;
    protected $type;
    protected $post_type;

    function __construct($name, $label, $post_type, $show_column=true, $type='CHAR')
    {
        $this->name = $name;
        $this->label = $label;
        $this->post_type = $post_type;
        $this->type = $type;
    
        if($show_column){
            $this->add_column();
        }
    }

    function get_label()
    {
        return $this->label;
    }

    function get_name()
    {
        return $this->name;
    }

    abstract function get_value($user_id);

    function filter(\WP_Query &$WP_Query, $condition, $value)
    {
        ;
    }
    
    function filter_args($args, $condition, $value)
    {
        return $args;
    }

    function add_column(){

        add_filter('manage_'.$this->post_type.'_posts_columns',function($columns){
            
            $columns[$this->get_name()] = $this->get_label();

            return $columns;
        });

        add_action('manage_'.$this->post_type.'_posts_custom_column',function( $column_name, $post_id){

            
            if ( $this->get_name() == $column_name ){
                echo $this->get_value($post_id);
            }

        },10,2);
    }
}
