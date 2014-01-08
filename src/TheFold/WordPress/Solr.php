<?php
namespace TheFold\Wordpress;

use \Solarium\Client;
use \TheFold\WordPress;
use \TheFold\WordPress\Cache;
use \TheFold\WordPress\ACF;

class Solr {
    
    use Cache; 

 const SETTING_NAMESPACE = 'thefold_wordpress_solr';
 
 protected static $instance;

 protected $hostname;
 protected $port;
 protected $path;
 protected $client;
 protected $facets = ['category'=>'always','tag'=>'always'];
 protected $last_resultset = null;
 protected $result_count = null;
 protected $per_page = null;
 protected $update_document;
 protected $pending_updates = [];
 protected $post_mapping = null;

 static function get_instance($path=null, $hostname=null, $port=null)
 {
    if(!static::$instance){
        static::$instance = new static($path, $hostname, $port);
    }

    return static::$instance;
 }

 function __construct($path=null, $hostname=null, $port=null)
 {
     $this->path = $path ?: WordPress::get_option(
         static::SETTING_NAMESPACE,'path','/solr/');
     $this->hostname = $hostname ?: WordPress::get_option(
         static::SETTING_NAMESPACE,'host','127.0.0.1');
     $this->port = $port ?: WordPress::get_option(
         static::SETTING_NAMESPACE,'port','8080');
 }

 function set_facets($facets)
 {
    $this->facets = $facets;
 }

 function get_posts($params=[])
 {
     if(!empty($params['cache_key']))
     {
         $posts = $this->cache_get($params['cache_key']);

         if(!is_null($posts)){
             return $posts; 
         }
     }

     $resultset = $this->get_resultset($params,false);

     $this->result_count = $resultset->getNumFound();

     $ids = array();

     foreach($resultset as $document){
         
         foreach($document as $field => $value){
             
             if($field == 'id')
                 $ids[] = $post_id = $value;
         }
     }

     $posts = $ids ? array_map( 'get_post', $ids ) : [];

     if(!empty($params['cache_key'])){
        $this->cache_set($params['cache_key'],$posts);
     }

     return $posts;
 }

 function get_facet($name, $qparams=null, $reuse=true)
 {
     $resultset = $this->get_resultset($qparams, $reuse);

     $facets = $resultset->getFacetSet()->getFacet($name);

     $return = [];
     foreach($facets as $value => $count){
         if($count) $return[$value] = $count;
     }

     ksort($return);

     return $return;
 }

 function get_facets($qparams=null)
 {
     if($qparams){
           $this->last_resultset = null; 
     }
     
     $facets = $this->facets();

     $return = array();
     foreach($facets as $facet){

         if($values = $this->get_facet($facet,$qparams,true)){
            
            $return[$facet] = $values; 
         }
     }

     ksort($return);

     return $return;
 }

 function get_result_count()
 {
     return $this->result_count;
 }

 function get_paging_links()
 {
    $return = [];
    
    parse_str($_SERVER['QUERY_STRING'],$qs);

    $current_page = isset($qs['page']) ? $qs['page'] : 1;

    $qs['page'] = $current_page + 1;
    if ($this->get_result_count() > $qs['page'] * $this->per_page)
        $return['next'] = http_build_query($qs);

    $qs['page'] = $current_page -1;
    if ($qs['page'] >= 1)
        $return['previous'] = http_build_query($qs);

    return str_replace('[0]','[]',$return);
 }

 public function update_post(\WP_Post $post, $mapping=null)
 {
     if($post->post_status == 'publish') {

         $solr_post = $this->get_update_post_document();

         if (!$mapping) {
             $mapping = $this->get_post_mapping();
         }

         if (!$mapping) {
             throw new \Exception('No post mapping data. Is your filter returning ?');
         }

         $author = get_userdata( $post->post_author );

         foreach ($mapping as $solr_field => $wp_post_field) {

             if (is_string($wp_post_field)) {
                 $value = $post->$wp_post_field;
             }
             elseif (is_callable($wp_post_field)){
                 $value = $wp_post_field($post, $author);
             }

             if(!is_null($value)){
                 $solr_post->addField($solr_field,$value);
             }
         }

         $this->pending_updates[$post->ID] = $solr_post;
     }
 }

 public function delete_post(\WP_Post $post)
 {
    $solr_post = $this->get_update_document();

    $solr_post->addDeleteById($post->ID);

    unset($this->pending_updates[$post->ID]);
 }

 public function deleteAll()
 {
     $update = $this->get_update_document();
     $update->addDeleteQuery('*:*');
     $update->addCommit();
     
     return $this->get_client()->update($update);
 }

 public function get_post_mapping()
 {
     if(!$this->post_mapping) {

         $this->post_mapping = [
             'id'=>'ID',
             'permalink'=>function($post){
                return get_permalink($post->ID);
             },
             'title'=>'post_title',
             'content'=> function($post) {
                return strip_tags($post->post_content);
             },
             'author' => function($post, $author) {
                return $author->display_name;
             },
             'author_s' => function($post, $author) {
                return get_author_posts_url($author->ID, $author->user_nicename);
             },
             'type' => 'post_type',
             'date' => function($post) {
                return $this->format_date($post->post_date_gmt);
             },
             'modified' => function($post) {
                return $this->format_date($post->post_modified_gmt);
             },
         ];

         $this->post_mapping = $this->map_taxonomies($this->map_custom_fields($this->post_mapping));

         $this->post_mapping = apply_filters('thefold_solr_post_mapping', $this->post_mapping);
     }

     return $this->post_mapping;
 }

 protected function map_custom_fields($post_mapping)
 {
    $custom_fields = WordPress::get_option(static::SETTING_NAMESPACE,'custom_fields');
    
    foreach($custom_fields as $field) {

        $is_date = false;
        $type = 's';

        if($meta = ACF::get_field_meta($field)){

            switch($meta['type']){

            case 'text':
                $type = 't';
                break;
            case 'true_false':
                $type = 'b';
                break;
            case 'date_time_picker':
                $type = 'dt'; 
                $is_date = true;
                break;
            }
        }

        $post_mapping["{$field}_{$type}"] = function($post) use ($field, $is_date){
            
            $value = get_post_meta($post->ID,$field,true);

            if($is_date && $value){
                $value = gmdate('Y-m-d\TH:i:s\Z',(int) $value); 
            }

            return $value ?: null;
        };
    }

    return $post_mapping;
 }

 protected function map_taxonomies($post_mapping)
 {
    $taxonomies = WordPress::get_option(static::SETTING_NAMESPACE,'taxonomies');

    foreach($taxonomies as $name) {

        $taxonomie = get_taxonomy($name);

        $schema_name = $taxonomie->name;

        if(!$taxonomie->_builtin){
            $schema_name .= '_srch';
        }

        $category_as_taxonomy = ($taxonomie->name == 'category' && Wordpress::get_option(static::SETTING_NAMESPACE,'category_as_taxonomy',1));

        // Index category and tag names
        $post_mapping[$schema_name] = function($post) use ($taxonomie, $category_as_taxonomy) {

            $names = null;

            if ($terms = get_the_terms($post,$taxonomie->name))
            {
                $names = [];

                foreach($terms as $term) {
                    $names[] = ($category_as_taxonomy) ? 
                        get_category_parents((int)$term->term_id, false, '^^') : 
                        $term->name;
                }
            }

            return $names;
        };
        
        // Index category and tag id's
        $post_mapping[$taxonomie->name.'_taxonomy'] = function($post) use ($taxonomie) {

            $names = null;

            if ($terms = get_the_terms($post,$taxonomie->name))
            {
                $names = [];

                foreach($terms as $term) {
                    $names[] = $term->term_id;
                }
            }

            return $names;
        };
    }

    return $post_mapping;
 }

 public function commit_pending()
 {
     $update = $this->get_update_document();

     $update->addDocuments($this->pending_updates);

     $update->addCommit();

     $result = $this->get_client()->update($update);

     $this->pending_updates = [];

     return $result;
 }

 protected function get_update_post_document()
 {
    return $this->get_update_document()->createDocument();
 }

 protected function get_resultset($params=[], $reuse=true)
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

     if(isset($params['query'])){
        $query->setQuery($params['query']);
     }

     if(isset($params['date_range'])) {

         $days_out = isset($params['date_range']['days_out']) ? $params['date_range']['days_out'] : 1;

         $from_date = isset($params['date_range']['from_date']) ? date('c',strtotime($params['date_range']['from_date'])).'Z/DAY' : 'NOW/DAY';

         $to_date = isset($params['date_range']['to_date']) ? date('c',strtotime($params['date_range']['to_date'])).'Z/DAY' : "{$from_date}+{$days_out}DAY";

         $date_field = isset($params['date_range']['date_field']) ? $params['date_range']['date_field'] : 'date';

         $query->createFilterQuery('daterange')->setQuery("$date_field:[$from_date TO $to_date]");
     }

     if(isset($params['post_types'])) {
         $query->createFilterQuery('posttype')->setQuery('type:('.implode(' OR ',(array) $params['post_types']).')');
     }

     if(isset($params['fields'])) {
        
         foreach($params['fields'] as $field => $value) {
             $query->createFilterQuery($field.'-query')->setQuery( $this->create_query_string($field, $value));
         }
     }

     foreach($this->facets(true) as $facet => $tag) {

         if(!empty($params['facets'][$facet])) {

             $query->addFilterQuery([
                 'field'=>$facet,
                 'key'=>$facet,
                 'query'=> $this->create_query_string($facet, $params['facets'][$facet]),
                 'tag' => $tag,
             ]);
         }
     }

     if( !empty($params['with_facets']) && $facets = $this->facets(true)) {

         $facetSet = $query->getFacetSet();

         foreach($facets as $field => $tag) {

             $facetSet->createFacetField([
                 'field'=>$field,
                 'key'=>$field,
                 'exclude'=>'user'
                 ]);
         }
     }


     if(isset($params['sort'])) {
         list($sort, $sort_type) = $params['sort'];
     }
     elseif(!isset($params['query'])) {
         //if not a search query, default to post date sort
         $sort = 'date';
         $sort_type = $query::SORT_DESC;
     }

     if($sort && $sort_type) {
         $query->addSort($sort, $sort_type);
     }

     if(isset($params['rows'])){
         $query->setRows($params['rows']);
     }
     elseif(!$params['nopaging']) {
         $query->setStart( ($params['page']-1) * $params['per_page'] )->setRows($params['per_page']);
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

 protected function get_update_document()
 {
     if(!$this->update_document){
         $this->update_document = $this->get_client()->createUpdate();
     }

     return $this->update_document;
 }

 protected function get_query(){

     return $this->get_client()->createSelect(['responsewriter' => 'phps']);
 }

 protected function exec_query($query){

     return $this->get_client()->select($query);
 }

 protected function get_config() {

     return [
         'endpoint' => [
             'localhost' => [
                 'hostname' => $this->hostname,
                 'port'     => $this->port,
                 'path' =>  $this->path
             ]
         ]
     ];
 }

 protected function create_query_string($field,$value){
     $values = array_map('urldecode', (array) $value);
     return $field.':('.implode(' OR ',$values).')';
 }

 public static function format_date($thedate){
    return gmdate('Y-m-d\TH:i:s\Z', is_numeric($thedate) ? $thedate : strtotime($thedate)); 
 }

}
