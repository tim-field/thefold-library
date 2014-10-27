<?php
namespace TheFold\FastPress\Solr\Facet;

class MultiQuery extends \TheFold\FastPress\Solr\Facet{

    public $name;
    public $label;
    public $queries;

    function __construct($name, $label='', $queries){
        $this->name = $name;
        $this->label = $label;
        $this->queries = $queries;
    }

    function get_name(){
        return $this->name;
    }

    function get_label(){
        return  $this->label ?: $this->name;
    }

    function create(\Solarium\QueryType\Select\Query\Component\FacetSet &$facetSet){
        $facet = $facetSet->createFacetMultiQuery($this->name);

        foreach($this->queries as $name => $query){
            $facet->createQuery($name, $query);
        }
        
        $facet->addExclude($this->name);
    }

    function apply($value){

       return $this->queries[$value];
    }
}
