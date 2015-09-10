<?php
namespace TheFold\FastPress\Solr\Facet;

class Query extends \TheFold\FastPress\Solr\Facet{

    public $name;
    public $label;
    public $query;

    function __construct($name, $label='', $query){
        $this->name = $name;
        $this->label = $label;
        $this->query = $query;
    }

    function get_name(){
        return $this->name;
    }

    function get_label(){
        return  $this->label ?: $this->name;
    }

    function create(\Solarium\QueryType\Select\Query\Component\FacetSet &$facetSet){
        $facetSet->createFacetQuery($this->name)
            ->setQuery($this->query)
            ->addExclude($this->name);
    }

    function apply($value){

       return $this->query;
    }
    
    function render($value, $count)
    {
        return $this->label.' ('.$count.')';
    }

    function parse_result( /* \Solarium\QueryType\Select\Result\Facet\Query */ $query){

        $return = [];
        $count = $query->getValue();

        if($count){
            $return[$this->name] = $count;
        }

        return $return;
    }
    
}
