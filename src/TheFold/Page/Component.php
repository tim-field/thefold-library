<?php

namespace TheFold\Page;

abstract class Component
{
    protected $posts;
    protected $wp_query;
    protected $plugin_url;
    protected $name;
    protected $js_name = null;
    protected $version = 1;

    function get_name() {

        if(!$this->name){
        
            $namespace = explode('\\',get_class($this));
            $this->name = end($namespace);
        }

        return $this->name;
    }
    
    function render(){
        ;
    }

    //TODO don't depend on this I want to remove it, should return empty string by default
    function json(){ ob_start(); $this->render(); return ob_get_clean(); }
   
    //function subscribe(\TheFold\Publication $publication)
    function subscribe($publication)
    {
        $publication->subscribe(function($result){

            if($result instanceof \WP_Query){
                $this->posts = $result->posts;
                $this->wp_query = $result;
            }
            //seems to be sub quirk of add action that an array with one value is passed with its value
            elseif(is_object($result)){
            
                $this->posts = [$result];
            
            } else {

                $this->posts = $result;
            }

        });
    }
 
    function get_js_name()
    {
        if(!$this->js_name) {

            // non-alpha and non-numeric characters become spaces
            $str = preg_replace('/[^a-z0-9]+/i', ' ', get_class($this));
            // uppercase the first character of each word
            $str = ucwords(trim($str));
            $this->js_name = str_replace(" ", "", $str);
        }

        return $this->js_name;
    }

    function get_js_handle() { return $this->get_js_name(); }

    function get_js_deps(){return [];}

    function get_js_config(){ return ['name'=>$this->get_name()];}

    function get_js_path()
    {
        //todo should test that file exists, maybe better to pass __DIR__ here.
        return trim($this->plugin_url,'/').'/js/components/'.$this->get_name().'.js';
    }

    function init_js($plugin_url){

        $this->plugin_url = $plugin_url;
        
        if($path = $this->get_js_path()){

            wp_enqueue_script($this->get_js_handle(), $path, $this->get_js_deps(), $this->version, true);

            if($config = $this->get_js_config()){
                wp_localize_script($this->get_js_handle(), $this->get_js_name().'Config',$config);
            }
        }
    }
}
