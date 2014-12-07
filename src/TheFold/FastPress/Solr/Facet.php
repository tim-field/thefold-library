<?php
namespace TheFold\FastPress\Solr;

abstract class Facet{

    abstract function get_name();

    abstract function create(\Solarium\QueryType\Select\Query\Component\FacetSet &$facetSet);

    abstract function apply($value);

    function get_label(){
        return $this->get_name();
    }
    
    function render($value, $count){
        return $value.' ('.$count.')';
    }
}
