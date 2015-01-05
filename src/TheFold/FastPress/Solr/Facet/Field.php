<?php
namespace TheFold\FastPress\Solr\Facet;

class Field extends \TheFold\FastPress\Solr\Facet{

    protected $field;
    protected $label;
    protected $excludes;

    function __construct($field, $label='', $excludes=[]){
        $this->field = $field;
        $this->label = $label;
        $this->excludes = array_merge([$this->field],(array) $excludes);
    }

    function get_field(){
        return $this->field;
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
            ->addExcludes($this->excludes);
    }

    function apply($value){

       return \TheFold\FastPress\Solr::create_query_string($this->get_filter_name(), $value); 
    }
}
