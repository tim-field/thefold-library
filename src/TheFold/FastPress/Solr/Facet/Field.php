<?php
namespace TheFold\FastPress\Solr\Facet;

class Field extends \TheFold\FastPress\Solr\Facet{

    protected $field;
    protected $label;
    protected $name;
    protected $excludes;

    function __construct($field, $label='', $excludes=[],$name=''){
        $this->field = $field;
        $this->name = $name ?: $field; //usefull if name conflicts with existing filterquery, ie post_status
        $this->label = $label;
        $this->excludes = array_merge([$this->name],(array) $excludes);
    }

    function get_field(){
        return $this->field;
    }

    function get_name(){
        return $this->name;
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

       return \TheFold\FastPress\Solr::create_query_string($this->get_field(), $value); 
    }
}
