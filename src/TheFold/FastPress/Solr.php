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

    const AUTO_COMMIT_AT = 300;

 protected $hostname;
 protected $port;
 protected $path;
 protected $client;
 protected $facets = [];// = ['category'=>'always','tag'=>'always'];
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
         'comment_count',
         'category_ancestors',
         'category_taxonomy'
     ];


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

     if($path && $hostname && $port){

         add_action('shutdown',function(){

             $this->commit_pending(); 
         },100);
     }
 }


 //interface 
 function index_post(\WP_Post $post)
 {
     $post_types = (array) apply_filters('fastpress_post_types', WordPress::get_option(FastPress::SETTING_NAMESPACE,'post_types'));

     if(in_array($post->post_type, $post_types)) {

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
     foreach($facets as $k => $v){

         if($v instanceof Solr\Facet){
            $this->facets[$v->get_name()] = $v;
         }
         elseif(is_string($v)){
             //legacy format 
             $this->facets[$k] = new Solr\Facet\Field($k);
        }
     }
     
     $this->with_facets = true;
 }

 static function format_date($thedate)
 {                                   
     //time must always be in UTC ( this is what Z represents) https://cwiki.apache.org/confluence/display/solr/Working+with+Dates
     //we strip php's +00:00 from the end of the date replace it with Z
     //note call to gmdate ( which gives us a UTC time )
     return preg_replace('#\+00:00$#','Z', gmdate('c',
         // catch timestamps
         (is_numeric($thedate) && strlen($thedate) == 10) ? $thedate : strtotime($thedate))); 
 }

  //interface
 function get_posts($params=[])
 {
     $params = array_merge([
         'wp_class' =>'WP_Post',
         'post_type' => 'post',
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

     if(!empty($params['cache_key'])){
         $this->cache_set($params['cache_key'],$posts);
     }

     if($posts){

         if(!isset($params['blogid']) || $params['blogid'] !== '*'){ 
             update_post_caches($posts, isset($params['post_type']) ? $params['post_type'] : 'post', true, true);
         }
         
         reset($posts);
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

 function get_stats()
 {
    return $this->last_resultset->getStats();
 }

 function get_count()
 {
     return $this->result_count;
 }


 public function update_post(\WP_Post $post)
 {
     $this->pending_updates[$this->get_solr_id($post->ID,'WP_Post')] = [ 'ID' => $post->ID, 'blog_id' => get_current_blog_id(), 'class'=> 'WP_Post' ];

     if(count($this->pending_updates) >= self::AUTO_COMMIT_AT){
         $this->commit_pending();
     }
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
         if(!is_array($data)){
             //already processed ? 
             user_error('Already processed ? Why is this happening',E_USER_WARNING);
             continue; 
         }

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

             $data = \TheFold\WordPress\Export::export_object($object,$mapping[$class]);

             $data = array_filter($data, function($val){
                    return !is_null($val); 
             });

             foreach($data as $field => $value){
                $solr_post->addField($field,$value);
             }

             $this->pending_updates[$solr_id] = $solr_post;
         }
         else{
            unset($this->pending_updates[$solr_id]);
            //add it as a delete then I guess. 
            $this->pending_deletes[$solr_id] = true;
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
     if(!isset($this->mapping['WP_Post'])) {
         $this->mapping['WP_Post'] = $this->get_post_mapping();
     }
     
     if(!isset($this->mapping['WP_User'])) {
         $this->mapping['WP_User'] = $this->get_user_mapping();
     }

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
                case 'google_map':
                    $type = 'p';
                    break;
                default:
                    $type = 's';
            }
        }
        
        $type = apply_filters('fastpress_custom_field_type',$type,$field);

        $post_mapping["{$field}_{$type}"] = function($post) use ($field, $type){
            
            $value = get_post_meta($post->ID,$field,true);

            if($value === ''){
                $value = $type == 'b' ? false : null;
            }
            elseif($type == 'dt'){
                $value = $this->format_date($value); 
            }
            elseif($type == 'i'){
                $value = (int) $value;
            }
            elseif($type == 'p'){
                $value = $value['lat'].','.$value['lng'];
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

     $taxonomies = array_unique(apply_filters('fastpress_taxonomies', (array) WordPress::get_option(FastPress::SETTING_NAMESPACE,'taxonomies')));

     if($taxonomies) foreach($taxonomies as $name) {

         $taxonomie = get_taxonomy($name);

         // Index category and tag names
         $post_mapping[$taxonomie->name.'_txt'] = function($post) use ($taxonomie) {

             $names = null;

             if ($terms = get_the_terms($post,$taxonomie->name))
             {
                 $names = [];

                 foreach($terms as $term) {
                     $names[] = $term->name;
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

                     if($taxonomie->name === 'category'){

                         $names = array_merge($names, explode('/',get_category_parents($term->term_id, false,'/', true)));
                     }
                 }

                 $names = array_unique(array_filter($names));
             }

             return $names;
         };

         // Index taxonomy ancestor path
         if(is_taxonomy_hierarchical($taxonomie->name)){

             $post_mapping[$taxonomie->name.'_ancestors'] = function($post) use ($taxonomie) {

                 $paths = [];

                 if ($terms = get_the_terms($post,$taxonomie->name)) {

                     foreach($terms as $term) {
                         $paths[] = Wordpress::get_term_parents((int)$term->term_id, $taxonomie->name ,false, '/', true);
                     }
                 }

                 return $paths;
             };
         }
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
         'fields' => [],
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

     if(isset($params['post_status'])){

         $ps_queries = [];
         $ps_status = [];
         
         foreach((array)$params['post_status'] as $status){
            
             if($status == 'private'){
                $ps_queries[] = sprintf('(post_author:%d AND post_status:"private")',get_current_user_id());
             }
             else{
                 $ps_status[] = $status;
             }
         }
         
         if($ps_status){
             $ps_queries[] = $this->create_query_string('post_status', $ps_status);
         }

         $query->createFilterQuery('post_status')->setQuery('('.implode(' OR ',$ps_queries).')');

         unset($params['post_status']);
     }

     if(isset($params['blogid']) && $params['blogid'] === '*'){
     
        $query->createFilterQuery('allblogs')->setQuery('blogid:*');
     }
     elseif(!isset($params['blogid']) && is_multisite() && $params['wp_class'] != 'WP_User')
     {
        $params['fields']['blogid'] = get_current_blog_id();
     }

     if(isset($params['date_range'])) {

         $days_out = isset($params['date_range']['days_out']) ? $params['date_range']['days_out'] : 1;

         $from_date = isset($params['date_range']['from_date']) ? self::format_date($params['date_range']['from_date']).'/DAY' : 'NOW/DAY';

         $to_date = isset($params['date_range']['to_date']) ? self::format_date($params['date_range']['to_date']).'/DAY+1DAY' : "{$from_date}+{$days_out}DAY";

         $date_field = isset($params['date_range']['date_field']) ? $params['date_range']['date_field'] : 'post_date_gmt';

         $query->createFilterQuery('daterange')
             ->setQuery("$date_field:[$from_date TO $to_date]")
             ->addTag('date_range');
     }

     if(isset($params['bounds'])){
         
         foreach($params['bounds'] as $field => $bounds){

             $bounds = explode(',', $bounds);

             $sw = implode(',',array_slice($bounds,0,2));
             $nw = implode(',',array_slice($bounds,2,4));
             $query->createFilterQuery($field)->setQuery("$field:[$sw TO $nw]")
                 ->addTags([$field,'bounds','fields']);
         }
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
             $query->createFilterQuery($field.'-field')
                 ->setQuery( $this->create_query_string($field, $value) )
                 ->addTags([$field,'fields']);
         }
     }

     if(!empty($params['fq'])) {
         foreach($params['fq'] as $name => $facet_query) {
             $query->createFilterQuery($name)->setQuery($facet_query);
         }
     }

     if(!empty($params['stats'])) {

         $stats = $query->getStats();

         foreach($params['stats']['fields'] as $field){
            
             $stat_field = $stats->createField($field);

             foreach($params['stats']['facets'] as $facet){
                $stat_field->addFacet($facet);
             }
         }
     }

     //Auto query field facets
     foreach($this->facets(true) as $facet) {

         $value = $facet->get_value($params);
         $name = $facet->get_filter_name();

         if(!is_null($value)) {
             $query->createFilterQuery($name)
                 ->setQuery($facet->apply($value))
                 ->addTags([$name,'facets']);
         }
     }

     if( !empty($params['with_facets']) && $facets = $this->facets(true)) {

         $facetSet = $query->getFacetSet();

         foreach($facets as $facet) {

            $facet->create($facetSet); 
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

 protected function facets($as_object=false){

     reset($this->facets);

     return $as_object ? $this->facets : array_keys($this->facets);
 }

 protected function get_client(){

     if(!$this->client){
         // create a client instance
         $this->client = new Client($this->get_config());

         do_action_ref_array('thefold-fastpress-solr-client-init',array(&$this));
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

 public static function create_query_string($field, $value)
 {
     if($value === true || $value === false){
        return $field.':'.($value ? 1 : 0);
     }

     return $field.':("'.implode('" "', (array) $value).'")';
 }

 protected function init_wp_object($id,$class){

    switch($class){
    
        case 'WP_User':
            return get_userdata($id);

        case 'WP_Post':
            return get_post($id);
    }

    throw new \Exception('Unknown class '.$class);
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
     $have_switched = false;

     if(is_multisite() && !empty($fields['blogid']) && $fields['blogid'] != get_current_blog_id()){
         switch_to_blog($fields['blogid']); 
         $have_switched = true;
     }

     $fields['post_date'] = date('Y-m-d H:i:s', strtotime($fields['post_date']));
     $fields['post_date_gmt'] = date('Y-m-d H:i:s', strtotime($fields['post_date_gmt']));
     
     $fields['post_modified'] = date('Y-m-d H:i:s', strtotime($fields['post_modified']));
     $fields['post_modified_gmt'] = date('Y-m-d H:i:s', strtotime($fields['post_modified_gmt']));

     $safe_fields = [];
     //todo only do this where needed
     foreach($fields as $field => $value) {
         $safe_fields[ strtolower(str_replace('-','_',$field)) ] = $value;
     }

     $safe_fields['ID'] = $fields['id'];
     unset($safe_fields['id']);

     $post = new \WP_Post((object)$safe_fields);

     if($have_switched){
         restore_current_blog();
     }

     return $post;
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
