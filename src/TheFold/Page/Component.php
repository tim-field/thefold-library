<?php

namespace TheFold\Page;

abstract class Component
{
    protected $posts;
    protected $wp_query;

    abstract function get_name();

    function init_js(){}

    function render(){ return '';}

    function json(){ ob_start(); $this->render(); return ob_get_clean(); }
   
    //function subscribe(\TheFold\Publication $publication)
    function subscribe($publication)
    {
        $publication->subscribe(function($result){

            //seems to be sub quirk of add action that an array with one value is passed with its value
            if($result instanceof \WP_Post){
                $this->posts = [$result];
            }

            else if($result instanceof \WP_Query){
                $this->posts = $result->posts;
                $this->wp_query = $result;
            }

            else {
                $this->posts = $result;
            }

        });
    }
}
