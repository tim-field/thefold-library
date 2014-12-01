<?php

namespace TheFold;

abstract class Page {

    protected $components;

    function add_component(Page\Component $component, $name)
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

    function get_facets()
    { 
        return array_map(function($component){
            
            if ($component instanceof Page\Component\Facet) {
                return $component->get_facet();
            }
        }, $this->components);
    }

    protected function init_js($path)
    {
        //todo try this
        //wp_register_script('thefold-page',plugin_dir_url($path).'/Page.js',['jquery'],null,true);

        foreach($this->components as $component){
            $component->init_js($path);
        }
    }

    abstract function render();

    protected function is_ajax()
    {
        return (defined('DOING_AJAX') && DOING_AJAX) || ( isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_GET['is_ajax']);
    }
}
