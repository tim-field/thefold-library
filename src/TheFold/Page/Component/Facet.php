<?php
/**
 * Todo this should be split into a parent Facet Component class
 * with init / get facet as an abstract method
 */
namespace TheFold\Page\Component;

use TheFold\WordPress;

abstract class Facet extends \TheFold\Page\Component{
    
    protected $facet_values = [];
    protected $facet = null;

    function __construct(){
        $this->facet = $this->get_facet();
    }

    function get_js_name()
    {
        return 'facet';
    }

    abstract function get_facet();

    function subscribe(\TheFold\Publication $publication)
    {
        $publication->subscribe_facets(function($facet_values){
            
            $this->facet_values = $facet_values[$this->facet->get_name()];
        });

        $publication->subscribe_query(function($query){

            if(isset($_GET[$this->get_js_name()][$this->facet->field])){
            
                $query['facets'][$this->facet->field] = urldecode($_GET[$this->get_js_name()][$this->facet->field]);
            }

            return $query;
        });
    }

    function format_values()
    {
        $facet = $this->get_facet();
        
        $return = [];
        foreach($this->facet_values as $value => $count){
            $return[$value] = $facet->render($value, $count);
        }

        return $return;
    }
    
    function render($view_params=[], $partial='partials/facet')
    {
        $facet = $this->get_facet();

        $view_params = array_replace_recursive($view_params,[
            'values' => $this->format_values(),
            'name' => $facet->get_name(),
            'label' => $facet->get_label()
        ]);

        WordPress::render_template($partial,null,$view_params);
    }
    
    function init_js($path)
    {
        //TODO load from correct locaiton
        wp_enqueue_script('thefold-component-facet', plugin_dir_url($path).'Facet.js',['thefold-page'],'1',true);

        wp_localize_script('thefold-component-facet',str_replace('\\','',get_called_class()).'Config',[
            'selector'=>'select.facet',
            'name' => $this->get_js_name()
        ]);
    }

    function json()
    {
       return []; //todo
    }
}
