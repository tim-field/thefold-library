<?php
namespace TheFold\FastPress\Solr\Facet;

class Geohash extends Field{

    protected $filter_name;

    /**
     * We filter on get_field with the level decremented
     * so that we display the markers from the previously selected
     * geohash
     */
    function get_filter_name()
    {
        if(!$this->filter_name)
        {
            //geohash_3_
            $facet_field = $this->get_field();

            //geohash_2
            $fq_field =  preg_replace_callback( "/_(\d+)_/", function($m){
                return '_'.($m[1]-1).'_'; 
            }, $facet_field);

            $this->filter_name = $fq_field;
        }

        return $this->filter_name;
    }
    
}
