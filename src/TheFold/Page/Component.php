<?php

namespace TheFold\Page;

abstract class Component
{
    protected $posts;

    abstract static function get_name();

    function init_js(){}

    function render($view_params=[],$partial=null){ 

        if($partial){

            $view_params = array_replace_recursive($view_params,[
                'self' => $this
            ]);

            \TheFold\WordPress::render_template($partial,null,$view_params);
        }
    }

    function json(){ ob_start(); $this->render(); return ob_get_clean(); }
   
    function subscribe(\TheFold\Publication $publication)
    {
        $publication->subscribe(function($posts){

            //seems to be sub quirk of add action that an array with one value is passed with its value
            if($posts instanceof \WP_Post){
                $posts = [$posts];
            }

            $this->posts = $posts;
        });
    }
}
