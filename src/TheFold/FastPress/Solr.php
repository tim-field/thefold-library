<?php

namespace TheFold\FastPress;

use \Solarium\Client;
use \TheFold\WordPress;
use \TheFold\FastPress;
use \TheFold\WordPress\Cache;
use \TheFold\WordPress\ACF;


class Solr implements Engine{
    
    use Cache; 

 protected static $instance;

 protected $hostname;
 protected $port;
 protected $path;
 protected $client;
 protected $facets = ['category'=>'always','tag'=>'always'];
 protected $last_resultset = null;
 protected $result_count = null;
 protected $update_document;
 protected $pending_updates = [];
 protected $pending_deletes = [];
 protected $post_mapping = null;
 protected $with_facets = false;
 protected $core_fields = [
         'ID',
         'id',
         'post_author',
         'post_name',
         'post_type',
         'post_title',
         'post_date',
         'post_date_gmt',
         'post_content',
         'post_excerpt',
         'post_status',
         'comment_status',
         'ping_status',
         'post_password',
         'post_parent',
         'post_modified',
         'post_modified_gmt',
         'menu_order',
         'post_mime_type',
         'comment_count'];


 static function get_instance($path=null, $hostname=null, $port=null)
 {
    if(!static::$instance){
        static::$instance = new static($path, $hostname, $port);
    }

    return static::$instance;
 }

 function __construct($path=null, $hostname=null, $port=null)
 {
     $this->path = $path;
     $this->hostname = $hostname;
     $this->port = $port;

     add_action('shutdown',function(){

         $this->commit_pending(); 
     });
 }


 //interface 
 function index_post(\WP_Post $post)
 {
     if(in_array($post->post_type, (array) WordPress::get_option(FastPress::SETTING_NAMESPACE,'post_types'))) {

         $this->update_post($post);

         return true;
     }

     return false; 
 }

 //interface
 function delete_post($post_id)
 {
    $solr_id = $this->get_solr_id($post_id);

    $this->pending_deletes[$solr_id] = true;

    unset($this->pending_updates[$solr_id]);
 }

 //interface
 function set_facets($facets=[])
 {
    $this->facets = $facets;
    $this->with_facets = true;
 }
 
 static function format_date($thedate){
    return gmdate('Y-m-d\TH:i:s\Z', is_numeric($thedate) ? $thedate : strtotime($thedate)); 
 }

 //interface
 function get_posts($params=[])
 {
     if(!empty($params['cache_key']))
     {
         $posts = $this->cache_get($params['cache_key']);

         if(!is_null($posts)){
             return $posts; 
         }
     }

     $posts = [];

     $resultset = $this->get_resultset($params,false);

     $this->result_count = $resultset->getNumFound();

     $hard_fetch = !empty($params['hard_fetch']);

     foreach($resultset as $document){

         if($hard_fetch){ 
             
             $posts[] = \WP_Post::get_instance($document['id']);

         } else {
             
            $posts[] = $this->init_wp_post($document->getFields());
         }
     }
    
     update_post_caches($posts, isset($params['post_type']) ? $params['post_type'] : 'post', true, true);

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

 //interface
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

 function get_count()
 {
     return $this->result_count;
 }


 public function update_post(\WP_Post $post)
 {
     $this->pending_updates[$this->get_solr_id($post->ID)] = [ 'ID' => $post->ID, 'blog_id' => get_current_blog_id() ];
 }

 protected function valid_status($status){
    
     return in_array($status, (array) WordPress::get_option(FastPress::SETTING_NAMESPACE,'post_status','publish'));
 }

 protected function proccess_pending()
 {
     if(empty($this->pending_updates))
        return;

     $mapping = $this->get_post_mapping();
    
     if (!$mapping) {
         throw new \Exception('No post mapping data. Is your filter returning ?');
     }

     foreach($this->pending_updates as $solr_id => $data)
     { 
         $post_id = $data['ID'];
         $blog_id = $data['blog_id'];

         if ( $blog_id && is_multisite() ){
             switch_to_blog($blog_id);
         }

         $post = get_post($post_id);

         if( $post && $this->valid_status($post->post_status))
         {
             $solr_post = $this->get_update_post_document();
             $author = get_userdata( $post->post_author );

             foreach ($mapping as $solr_field => $wp_post_field) {

                 $value = null;

                 if (is_string($wp_post_field)) {
                     $value = $post->$wp_post_field;
                 }
                 elseif (is_callable($wp_post_field)){
                     $value = $wp_post_field($post, $author, $blog_id);
                 }

                 if(!is_null($value)){
                     $solr_post->addField($solr_field,$value);
                 }
             }

             //error_log('updating post with solr '.$post->ID." to blog ".(get_current_blog_id())."\n",3,'/tmp/thefold-solr.log');

             $this->pending_updates[$solr_id] = apply_filters('thefold_fastpress_update_post', $solr_post, $post);

         }
         else{
            unset($this->pending_updates[$solr_id]);
         }

         if ( is_multisite() ) {
             restore_current_blog();
         }
     }
 }


 public function delete_all()
 {
     $update = $this->get_update_document();
     
     if(is_multisite()) {
         $update->addDeleteQuery('blogid:'.get_current_blog_id());

     } else {
         $update->addDeleteQuery('*:*');
     }

     $update->addCommit();
     
     return $this->get_client()->update($update);
 }

 public function get_post_mapping()
 {
     if(!$this->post_mapping) {

         $this->post_mapping = [

             'solr_id' => function($post){
                return $this->get_solr_id($post->ID);
             },
             'blogid' => function($post_id,$author){
                return get_current_blog_id();
             },

             'id' => 'ID',
             'post_author' => function($post,$author){
                return $author->display_name;
             },
             'post_name' => 'post_name',
             'post_type' => 'post_type',
             'post_title' => function($post) {
                return apply_filters('the_title',$post->post_title);
             },
             'post_date' => function($post) {
                return $this->format_date($post->post_date);
             },
             'post_date_gmt' => function($post) {
                return $this->format_date($post->post_date_gmt);
             },
             'post_content' => function($post) {
                return $post->post_content ?: null;
             },
             'post_excerpt' => function($post) {
	        return $this->post_excerpt ?: null;
             },
             'post_status' => 'post_status',
             'comment_status' => function($post) {
                return $post->comment_status == 'open' ? true : false;
             },
             'ping_status' => function($post) {
                return $post->ping_status == 'open' ? true : false;
             },
             'post_parent' => 'post_parent',
             'post_modified' => function($post) {
                return $this->format_date($post->post_modified);
             },
             'post_modified_gmt' => function($post) {
                return $this->format_date($post->post_modified_gmt);
             },
             'comment_count' => 'comment_count',
             'menu_order' => 'menu_order',
             'post_mime_type' => 'post_mime_type',
             'permalink' => function($post){
                return get_permalink($post->ID);
             },
             'author_id' => function($post, $author) {
                return $author->ID;
             },

         ];
            
         $this->post_mapping = $this->map_taxonomies($this->map_custom_fields($this->post_mapping));

         $this->post_mapping = apply_filters('fastpress_post_mapping', $this->post_mapping);
     }

     return $this->post_mapping;
 }

 protected function map_custom_fields($post_mapping)
 {
    $custom_fields = WordPress::get_option(FastPress::SETTING_NAMESPACE,'custom_fields');
    
    if($custom_fields) foreach($custom_fields as $field) {

        $type = 's';

        //TODO need a better way test that ACF plugin is active.
        if (function_exists('\get_field_object')) {

            switch(ACF::get_instance()->get_field_type($field)){
                case 'true_false':
                    $type = 'b';
                    break;
                case 'number':
                    $type = 'f';
                    break;
                case 'date_time_picker':
                case 'date_picker':
                    $type = 'dt'; 
                    $is_date = true;
                    break;
                default:
                    $type = 's';
            }
        }
        
        $type = apply_filters('fastpress_custom_field_type',$type,$field);

        $post_mapping["{$field}_{$type}"] = function($post) use ($field, $type){
            
            $value = get_post_meta($post->ID,$field,true);

            if($value === ''){
                $value = null;
            }
            elseif($type == 'dt'){
                $value = $this->format_date($value); 
            }
            elseif($type == 'i'){
                $value = (int) $value;
            }

            return apply_filters('fastpress_custom_field_value', 
                apply_filters('fastpress_custom_field_value_'.$field, $value, $field),
                $field);
        };
    }

    return $post_mapping;
 }

 protected function map_taxonomies($post_mapping)
 {
     /**
      * Todo there is more to do in this function around heiracy
      *
      * the get_ancestors function might be good
      */

    $taxonomies = WordPress::get_option(FastPress::SETTING_NAMESPACE,'taxonomies');

    foreach($taxonomies as $name) {

        $taxonomie = get_taxonomy($name);

        $schema_name = $taxonomie->name;

        $schema_name .= '_txt';

        $category_as_taxonomy = ($taxonomie->name == 'category' && Wordpress::get_option(FastPress::SETTING_NAMESPACE,'category_as_taxonomy',1));

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

        
        // Index category and tag slugs
        $post_mapping[$taxonomie->name.'_taxonomy'] = function($post) use ($taxonomie) {

            $names = null;

            if ($terms = get_the_terms($post,$taxonomie->name))
            {
                $names = [];

                foreach($terms as $term) {
                    $names[] = $term->slug;
                }
            }

            return $names;
        };
    }

    return $post_mapping;
 }

 public function commit_pending()
 {
     $this->proccess_pending();

     $result = null;

     $update = null;

     if($this->pending_updates) {

         $update = $this->get_update_document();

         $update->addDocuments($this->pending_updates);
     }

     if($this->pending_deletes) {

         if(!$update){
             $update = $this->get_update_document();
         }

         $update->addDeleteByIds(array_keys($this->pending_deletes));
     }

     if($update){
     
         $update->addCommit();
         
         $result = $this->get_client()->update($update);
     }

     $this->pending_updates = [];
     $this->pending_deletes = [];

     return $result;
 }

 protected function get_update_post_document()
 {
    return $this->get_update_document()->createDocument();
 }

 protected function get_resultset($params=[], $reuse=true)
 {
     if((!$this->last_resultset || !$reuse) && $params) {
         $this->last_resultset = $this->exec_query($this->build_query($params));
     }

     return $this->last_resultset;
 }

 protected function build_query($params=array())
 {
     $default_params = [
         'nopaging' => false,
         'page' => 1,
         'with_facets' => $this->with_facets
     ];

     $params = array_merge($default_params, $params);

     $query = $this->get_query();

     //$query->setFields(array('id'));

     if(isset($params['boost'])){
         $dismax = $query->getDisMax();
         $dismax->setBoostQuery($params['boost']);
     }

     if(isset($params['query'])){
        $query->setQuery($params['query']);
     }

     if(!isset($params['blogid']) && is_multisite())
     {
        $params['fields']['blogid'] = get_current_blog_id();
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

     $params['fields'] = array_merge(array_intersect_key($params,array_flip($this->core_fields)), (array) $params['fields']);

     if(!empty($params['fields'])) {
        
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

     $sorts = [];

     if(isset($params['sorts'])) {

         $sorts = $params['sorts'];
     }
     elseif(isset($params['sort'])) {

         list($sort, $sort_type) = $params['sort'];

         $sorts[$sort] = strtolower($sort_type);
     }
     elseif(!isset($params['query'])) {
         //if not a search query, default to post date sort

         $sorts['post_date'] = $query::SORT_DESC;
     }

     if($sorts) {
         $query->addSorts($sorts);
     }

     if(isset($params['rows'])){
         $query->setRows($params['rows']);
     }
     elseif(!$params['nopaging']) {
         $query->setStart( ($params['page']-1) * $params['posts_per_page'] )->setRows($params['posts_per_page']);
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

 protected function create_query_string($field, $value)
 {
     return $field.':("'.implode('" "', (array) $value).'")';
 }

 public function get_solr_id($post_id)
 {
    return $post_id.':'.get_current_blog_id();
 }

 public function registerPlugin($name, $object)
 {
    $this->get_client()->registerPlugin($name,$object); 
 }

 //TODO add to interface
 public function admin_init()
 {
     //Admin::get_instance(self::SETTING_NAMESPACE); 
 }

 //take returned solr fields and return a wp post object
 protected function init_wp_post($fields)
 {
     /**
      * Todo fix this
      $fields['post_date'] = date('Y-m-d H:i:s',strtotime($fields['post_date']));
     $fields['post_date_gmt'] = date('Y-m-d H:i:s',strtotime($fields['post_date_gmt']));
      */
     $fields['post_date'] = date('Y-m-d H:i:s', strtotime($fields['post_date']));
     $fields['post_date_gmt'] = date('Y-m-d H:i:s', strtotime($fields['post_date_gmt']));
     
     $fields['post_modified'] = date('Y-m-d H:i:s', strtotime($fields['post_modified']));
     $fields['post_modified_gmt'] = date('Y-m-d H:i:s', strtotime($fields['post_modified_gmt']));

     $safe_fields = [];

     foreach($fields as $field => $value) {
         $safe_fields[ strtolower(str_replace('-','_',$field)) ] = $value;
     }

     $safe_fields['ID'] = $fields['id'];
     unset($safe_fields['id']);

     return new \WP_Post((object)$safe_fields);
 }

}
