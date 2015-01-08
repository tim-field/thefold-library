<?php

namespace TheFold;

abstract class Page extends Page\Component{

    protected $components = [];

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
        return array_filter(array_map(function($component){
            
            if ($component instanceof Page\Component\Facet) {
                return $component->get_facet();
            }
        }, $this->components));
    }
    
    function get_js_path()
    {
        return trim($this->plugin_url,'/').'/js/'.$this->get_name().'.js';
    }

    function init_js($plugin_url)
    {
        foreach($this->components as $component){
            $component->init_js($plugin_url);
        }

        wp_enqueue_script('TheFoldPage', trim($plugin_url,'/').'/js/Page.js',['jquery','underscore'], $this->version, true);

        parent::init_js($plugin_url);
    }

    function get_js_deps()
    {
        $deps = array_map(function($component){
            return $component->get_js_handle(); 

        }, $this->components);

        $deps[] = 'TheFoldPage';

        return $deps;
    }

    protected function is_ajax()
    {
        return (defined('DOING_AJAX') && DOING_AJAX) || ( isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_GET['is_ajax']);
    }
}
