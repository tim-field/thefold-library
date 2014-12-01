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
            
            $this->posts = $posts;
        });
    }
}
