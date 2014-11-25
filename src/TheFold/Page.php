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

    protected function init_js()
    {
        //Todo how to get this accessable ? CDN ?
        //wp_register_script('thefold-page',plugin_dir_url(__FILE__).'/Page.js',['jquery'],null,true);

        foreach($this->components as $component){
            $component->init_js();
        }
    }

    abstract function render();
}
