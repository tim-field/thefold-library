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
 protected $user_mapping = null;
 protected $mapping = null;
 protected $with_facets = false;
 protected $core_post_fields = [
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

 function index_user(\WP_User $user)
 {
    $this->update_user($user);

    return true;
 }

 //interface
 function delete_post($post_id)
 {
    $solr_id = $this->get_solr_id($post_id,'WP_Post');

    $this->pending_deletes[$solr_id] = true;

    unset($this->pending_updates[$solr_id]);
 }

 function delete_user($user_id)
 {
    $solr_id = $this->get_solr_id($user_id,'WP_User');

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
     $params = array_merge([
         'wp_class' =>'WP_Post'
         ],$params
     );

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

 //interface
 function get_users($params=[])
 {
     $params = array_merge([
         'wp_class' =>'WP_User'
         ],$params
     );

     $users = [];
     $ids= [];

     $resultset = $this->get_resultset($params,false);
     
     $this->result_count = $resultset->getNumFound();
     
     foreach($resultset as $document){

         $fields = $document->getFields();
         $users[] = $this->init_wp_user($fields);
         $ids[] = $fields['id']; 
     }

     cache_users($ids);

     return $users;
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
     $this->pending_updates[$this->get_solr_id($post->ID,'WP_Post')] = [ 'ID' => $post->ID, 'blog_id' => get_current_blog_id(), 'class'=> 'WP_Post' ];
 }

 public function update_user(\WP_User $user)
 {
    $this->pending_updates[$this->get_solr_id($user->ID,'WP_User')] = ['ID' =>$user->ID, 'blog_id' => get_current_blog_id(), 'class'=> 'WP_User'];
 }

 protected function valid_status($status){
    
     return in_array($status, (array) WordPress::get_option(FastPress::SETTING_NAMESPACE,'post_status','publish'));
 }

 protected function valid_role($roles){

    foreach($roles as $role){

        if ( in_array($role, (array) WordPress::get_option(FastPress::SETTING_NAMESPACE,'user_roles')) ){
            return true;
        }
    }

    return false;
 }

 protected function proccess_pending()
 {
     if(empty($this->pending_updates))
        return;

     $mapping = $this->get_mapping();
    
     if (!$mapping) {
         throw new \Exception('No post mapping data. Is your filter returning ?');
     }

     foreach($this->pending_updates as $solr_id => $data)
     { 
         $post_id = $data['ID'];
         $id = $data['ID'];
         $blog_id = $data['blog_id'];
         $class = $data['class'];

         //this probably isn't needed unless this function was called outside of page rendering
         if ( $blog_id && is_multisite() ){
             switch_to_blog($blog_id);
         }

         $object = $this->init_wp_object($id,$class);

         if( $object && $this->ok_to_index($object))
         {
             $solr_post = $this->get_document();

             foreach ($mapping[$class] as $solr_field => $wp_field) {

                 $value = null;

                 if (is_string($wp_field)) {
                     $value = $object->$wp_field;
                 }
                 elseif (is_callable($wp_field)){
                     $value = $wp_field($object, $blog_id);
                 }

                 if(!is_null($value)){
                     $solr_post->addField($solr_field,$value);
                 }
             }

             $this->pending_updates[$solr_id] = apply_filters('thefold_fastpress_update_'.($class == 'WP_Post' ? 'post' : 'user'), $solr_post, $object);

         }
         else{
            unset($this->pending_updates[$solr_id]);
         }

         if ( is_multisite() ) {
             restore_current_blog();
         }
     }
 }


 public function delete_all($query = null)
 {
     $update = $this->get_update_document();
     
     if(empty($query)){

         if(is_multisite()) {
             $query = 'blogid:'.get_current_blog_id();

         } else {
             $query = '*:*';
         }
     }
     
     $update->addDeleteQuery($query);

     $update->addCommit();
     
     return $this->get_client()->update($update);
 }

 public function get_mapping()
 {
    $this->mapping['WP_Post'] = $this->get_post_mapping();
    $this->mapping['WP_User'] = $this->get_user_mapping();

    return $this->mapping;
 }

 public function get_user_mapping()
 {

    if(!$this->user_mapping){
    
        $this->user_mapping = [
            
            'solr_id' => function($user){
                return $this->get_solr_id($user->ID,'WP_User');
            },

            'id' => 'ID',

            'caps' => 'caps',
            'cap_key' => 'cap_key',
            'roles' => 'roles',

            //users
            'user_login' => 'user_login',
            'user_nicename' => 'user_nicename',
            'user_email' => 'user_email',
            'user_url' => 'user_url',
            'display_name' => 'display_name',

            //meta
            'user_firstname' => 'user_firstname',
            'user_lastname' => 'user_lastname',
            'description' => 'description',
            'nickname' => 'nickname',
            'source_domain' => 'source_domain',

            'wp_class' => function($user){
                return 'WP_User';
            }
        ];

        //If you want anymore use the hook. Meta fields is a can of worms
        
        $this->user_mapping = apply_filters('fastpress_user_mapping', $this->user_mapping);
    }

    return $this->user_mapping;
 }

 public function get_post_mapping()
 {
     if(!$this->post_mapping) {

         $this->post_mapping = [

             'solr_id' => function($post){
                return $this->get_solr_id($post->ID,'WP_Post');
             },
             'blogid' => function($post){
                return get_current_blog_id();
             },

             'id' => 'ID',
             'post_author' => 'post_author', //This is the users id
             'post_name' => 'post_name',
             'post_type' => 'post_type',
             'post_title' => 'post_title',
             'post_date' => function($post) {
                return $this->format_date($post->post_date);
             },
             'post_date_gmt' => function($post) {
                return $this->format_date($post->post_date_gmt);
             },
             'post_content' => 'post_content',
             'post_excerpt' => 'post_excerpt',
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
             'wp_class' => function($post){
                return 'WP_Post';
             }

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

    if($taxonomies) foreach($taxonomies as $name) {

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

 protected function get_document()
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
         'rows' => 1000,
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

     if(!isset($params['blogid']) && is_multisite() && $params['wp_class'] != 'WP_User')
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

     if(isset($params['wp_class'])){
        $query->createFilterQuery('wp_class')->setQuery('wp_class:'.$params['wp_class']);
     }

     if(isset($params['post_types'])) {
         $query->createFilterQuery('posttype')->setQuery('type:('.implode(' OR ',(array) $params['post_types']).')');
     }

     $params['fields'] = array_merge(array_intersect_key($params,array_flip($this->core_post_fields)), (array) $params['fields']);

     if(!empty($params['fields'])) {
        
         foreach($params['fields'] as $field => $value) {
             $query->createFilterQuery($field.'-query')->setQuery( $this->create_query_string($field, $value));
         }
     }

     foreach($this->facets(true) as $facet => $tag) {

         $value = null;  

         if(!empty($params['facets'][$facet])) {
            $value = $params['facets'][$facet];
         }
         //Auto pull from get if avaiable. Hacky? Useful tho
         elseif(!empty($_GET[$facet])){ 
            $value = urldecode($_GET[$facet]);
         }

         if(!is_null($value)) {
             $query->addFilterQuery([
                 'field'=>$facet,
                 'key'=>$facet,
                 'query'=> $this->create_query_string($facet, $value),
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
     elseif(!isset($params['query']) && $params['wp_class'] == 'WP_Post') {
         //if not a search query, default to post date sort
        $sorts['post_date'] = $query::SORT_DESC;
     }

     if($sorts) {
         $query->addSorts($sorts);
     }

     if($params['nopaging']) {
         $query->setRows($params['rows']);
     }
     else {
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

 protected function init_wp_object($id,$class){

    switch($class){
    
        case 'WP_User':
            return get_userdata($id);

        case 'WP_Post':
            return get_post($id);
    }  

    throw new Exception('Unknown class '.$class);
 }

 /**
  * Return true if we should index this object 
  */
 protected function ok_to_index($object)
 {
     if($object instanceof \WP_Post){
        return $this->valid_status($object->post_status);
     }

     if($object instanceof \WP_User){
        return $this->valid_role($object->roles);
     }
     
     return true;
 }

 public function get_solr_id($wp_id, $class='WP_Post')
 {
    if($class == 'WP_Post'){ 
        return $wp_id.':'.get_current_blog_id();  
    }
    elseif ($class== 'WP_User') {
        return $wp_id.':'.$class;
    }
    else {
        throw new \Exception('Don\'t know how to index class '.$class);
    }
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

 protected function init_wp_user($fields)
 {
     $safe_fields = [];

     foreach($fields as $field => $value) {
         $safe_fields[ strtolower(str_replace('-','_',$field)) ] = $value;
     }

     $safe_fields['ID'] = $fields['id'];
     unset($safe_fields['id']);

     return new \WP_User((object)$safe_fields);
 }

}
