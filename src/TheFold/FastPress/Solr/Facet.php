<?php
namespace TheFold\FastPress\Solr;

abstract class Facet{

    protected $value;

    abstract function get_name();

    abstract function create(\Solarium\QueryType\Select\Query\Component\FacetSet &$facetSet);

    abstract function apply($value);

    function get_value($params=[]) 
    {
        $value = null;
        $name = $this->get_filter_name();

        if(!empty($params['facets'][$name])) {
            $value = $params['facets'][$name];
        }
        //Auto pull from get if avaiable. Hacky? Useful tho
        elseif(!empty($_GET[$name])){ 
            $value = urldecode($_GET[$name]);
        }

        return $this->value = $value;
    }

    function get_label()
    {
        return $this->get_name();
    }
    
    function render($value, $count)
    {
        return $value.' ('.$count.')';
    }

    /**
     * The name that solr looks for in the request when applying the facet 
     */ 
    function get_filter_name()
    {
        return $this->get_name();
    }
    
    function render_results($params=[]) 
    {
        $params = array_replace_recursive([
            'template'=>'partials/facet',
            'name'=>$this->name,
            'label'=>$this->get_label()
        ], $params);

        $name = strtolower( (new \ReflectionClass(get_called_class()))->getShortName() );

        \TheFold\WordPress::render_template($params['template'], $name, $params); 
    }

    function parse_result(/*\Solarium\QueryType\Select\Result\Facet*/ $fields){
        $return = [];

        foreach($fields as $value => $count){
            if($count) $return[$value] = $count;
        }

        ksort($return);

        return $return;
    }
}
