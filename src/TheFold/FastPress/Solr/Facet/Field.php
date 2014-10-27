<?php
namespace TheFold\FastPress\Solr\Facet;

class Field extends \TheFold\FastPress\Solr\Facet{

    public $field;
    public $label;

    function __construct($field, $label=''){
        $this->field = $field;
        $this->label = $label;
    }

    function get_name(){
        return $this->field;
    }

    function get_label(){
        return  $this->label ?: $this->field;
    }

    function create(\Solarium\QueryType\Select\Query\Component\FacetSet &$facetSet){
        $facetSet->createFacetField($this->field)
            ->setField($this->field)
            ->addExclude($this->field);
    }

    function apply($value){

       return \TheFold\FastPress\Solr::create_query_string($this->field, $value); 
    }
}
