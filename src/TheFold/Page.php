<?php

namespace TheFold;

abstract class Page {

    protected $components;

    function add_component(\TheFold\Page\Component $component, $name)
    {
        $this->components[$name] = $component;    
    }

    function component($name)
    {
        return $this->components[$name]; 
    }
    
    function json() {

        $json = [];

        foreach($this->components as $name => $component){
            
            $json[$name] = $component->json();
        }

        return $json;
    }

    protected function init_js()
    {
        //Todo how to get this accessable ? CDN ?
        //wp_register_script('thefold-page',plugin_dir_url(__FILE__).'/Page.js',['jquery'],null,true);

        foreach($this->components as $component){
            $component->init_js();
        }
    }

    abstract function render();

    protected function is_ajax()
    {
        return (defined('DOING_AJAX') && DOING_AJAX) || ( isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_GET['is_ajax']);
    }
}
