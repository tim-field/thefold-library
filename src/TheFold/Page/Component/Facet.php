<?php

namespace TheFold\Page\Component;

use TheFold\WordPress;

abstract class Facet extends \TheFold\Page\Component{
    
    protected $facet_values = [];
    protected $facet;

    function __construct()
    {
        $this->facet = $this->get_facet();
    }

    abstract function get_facet();

    /*function subscribe(\TheFold\Publication $publication)*/
    function subscribe($publication) //temp
    {
        $publication->subscribe_facets(function($facet_values){
            
            $this->set_facet_values($facet_values);
        });

        $publication->subscribe_query(function($query){

            return $this->set_query_value($query);
        });
    }

    function format_values()
    {
        $return = [];
        foreach($this->facet_values as $value => $count){
            $return[$value] = $this->facet->render($value, $count);
        }

        return $return;
    }
    
    function render($view_params=[], $partial='partials/facet')
    {
        $view_params = array_replace_recursive($view_params,[
            'facet_id' => 'facet_'.$this->get_name(),
            'values' => $this->format_values(),
            'name' => $this->get_name(),
            'label' => $this->facet->get_label(),
            'selected' => $this->get_query_value()
        ]);

        WordPress::render_template($partial,null,$view_params);
    }
   
    function get_js_path()
    {
        return trim($this->plugin_url,'/').'/js/components/Facet.js';
    }

    function get_js_handle()
    {
        return 'TheFoldPageComponentFacet'; 
    }
    
    function get_js_config()
    {
        return [
            'selector'=>'#facet_'.$this->get_name(),
            'name' => $this->get_name()
        ];
    }

    protected function set_facet_values($facet_values)
    {
        if(isset($facet_values[$this->facet->get_name()])){
            $this->facet_values = $facet_values[$this->facet->get_name()];
        }
        else {
            $this->facet_values = [];
        }
        return $this->facet_values;
    }

    protected function get_query_value()
    {
        if(isset($_GET[$this->get_name()])){

            return urldecode($_GET[$this->get_name()]);
        }
    }

    protected function set_query_value($query) {

        if($value = $this->get_query_value()){

            $query['facets'][$this->facet->get_filter_name()] = $value;
        }

        return $query;
    }
}
