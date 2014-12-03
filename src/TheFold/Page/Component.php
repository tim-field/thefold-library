<?php

namespace TheFold\Page;

abstract class Component
{
    protected $posts;

    abstract static function get_name();

    function init_js(){}

    function render(){ return '';}

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
