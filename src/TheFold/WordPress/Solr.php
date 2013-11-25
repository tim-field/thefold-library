<?php
namespace TheFold\Wordpress;

use TheFold\Log;
use \Solarium\Client;

/*
 * Plugin Name: Solr Query 
 * Description : Use Solr instead of the DB to get posts
 * 
 */

class Solr{

 protected $hostname;
 protected $port;
 protected $path;
 protected $client;
 protected $facets = array('categories'=>'always','tags'=>'always');
 protected $last_resultset = null;
 protected $result_count = null;
 protected $per_page = null;

 function __construct($path, $hostname='127.0.0.1', $port=8080)
 {
     $this->path = $path;
     $this->hostname = $hostname;
     $this->port = $port; 
 }

 function set_facets($facets)
 {
    $this->facets = $facets;
 }

 function get_posts($params=array())
 {
     $resultset = $this->get_resultset($params);

     $this->result_count = $resultset->getNumFound();

     $ids = array();

     foreach($resultset as $document){
         
         foreach($document as $field => $value){
             
             if($field == 'id')
                 $ids[] = $post_id = $value;
         }
     }

     return $ids ? array_map( 'get_post', $ids ) : array();
 }

 function get_facet($name, $qparams=array())
 {
     $resultset = $this->get_resultset($qparams);

     $facets = $resultset->getFacetSet()->getFacet($name);

     $return = array();
     foreach($facets as $value => $count){
         if($count) $return[$value] = $count;
     }

     return $return;
 }

 function get_result_count()
 {
     return $this->result_count;
 }

 function get_paging_links()
 {
    $return = array();
    
    parse_str($_SERVER['QUERY_STRING'],$qs);

    $current_page = isset($qs['page']) ? $qs['page'] : 1;

    $qs['page'] = $current_page + 1;
    if($this->get_result_count() > $qs['page'] * $this->per_page)
        $return['next'] = http_build_query($qs);

    $qs['page'] = $current_page -1;
    if($qs['page'] >= 1)
        $return['previous'] = http_build_query($qs);

    return str_replace('[0]','[]',$return);
 }

 protected function get_resultset($params=array(), $reuse=true)
 {
     if(!$this->last_resultset || !$reuse){
         $this->last_resultset = $this->exec_query($this->build_query($params));
     }

     return $this->last_resultset;
 }

 protected function build_query($params=array())
 {
     $default_params = array(
         'nopaging'=>false,
         'page'=>1,
         'per_page'=>5
     );

     $params = array_merge($default_params, $params);

     $this->per_page = $params['per_page'];

     $query = $this->get_query();

     $query->setFields(array('id'));

     if(isset($params['date_range'])) {

         $from_date = (isset($params['date_range']['from_date'])) ? date('c',strtotime($params['date_range']['from_date'])).'Z/DAY' : 'NOW/DAY';
         $to_date = (isset($params['date_range']['to_date'])) ? date('c',strtotime($params['date_range']['to_date'])).'Z/DAY' : 'NOW/DAY+3DAY';
         $query->createFilterQuery('daterange')->setQuery("date:[$from_date TO $to_date]");
     }

     //need an array here !
     if(isset($params['post_types'])) {
         $query->createFilterQuery('posttype')->setQuery('type:('.implode(' OR ',$params['post_types']).')');
     }

     if(isset($params['fields'])) {
        
         foreach($params['fields'] as $field => $value) {
             $query->createFilterQuery($field.'-query')->setQuery( $this->create_query_string($field, $value));
         }
     }

     foreach($this->facets(true) as $facet => $tag){

         if(!empty($params['facets'][$facet])){

             $query->addFilterQuery(array(
                 'field'=>$facet,
                 'key'=>$facet,
                 'query'=> $this->create_query_string($facet, $params['facets'][$facet]),
                 'tag' => $tag,
             ));
         }
     }

     if($facets = $this->facets(true)) {

         $facetSet = $query->getFacetSet();

         foreach($facets as $field => $tag) {
             $facetSet->createFacetField(array(
                 'field'=>$field,
                 'key'=>$field,
                 'exclude'=>'user')
             );
         }
     }

     if(isset($params['sort'])) {
         list($sort, $type) = $params['sort'];
     }
     else {
         $query->addSort('date', $query::SORT_DESC);
     }

     if(!$params['nopaging']) {
         $query->setStart( ($params['page']-1) * $params['per_page'] )->setRows($params['per_page']);
         Log::add(($params['page']-1) * $params['per_page']);
     }

     return $query;
 } 

 protected function facets($with_tags=false){

     return $with_tags ? $this->facets : array_keys($this->facets);
 }

 protected function get_client(){

     if(!$this->client){
         // create a client instance
         $this->client = new Client($this->get_config());
     }

     return $this->client;
 }

 protected function get_query(){

     return $this->get_client()->createSelect(array('responsewriter' => 'phps'));
 }

 protected function exec_query($query){

     return $this->get_client()->select($query);
 }

 protected function get_config() {

     return array(
         'endpoint' => array(
             'localhost' => array(
                 'hostname' => $this->hostname,
                 'port'     => $this->port,
                 'path' =>  $this->path
             )
         )
     );
 }

 protected function create_query_string($field,$value){
     $values = array_map('urldecode', (array) $value);
     return $field.':('.implode(' OR ',$values).')';
 }


}
