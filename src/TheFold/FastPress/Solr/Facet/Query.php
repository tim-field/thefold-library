<?php
namespace TheFold\FastPress\Solr\Facet;
//untested
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
            ->setQuery($query)
            ->addExclude($this->name);
    }

    function apply($value){

       return $this->query;
    }
}
