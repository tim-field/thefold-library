<?php

namespace TheFold;

use \TheFold\FastPress\Solr;
use \TheFold\FastPress\Admin;
use \TheFold\FastPress\Engine;
use TheFold\Singleton;


class FastPress implements Engine
{
    use Singleton; 

    protected $engine;
    protected $posts;
    protected $current_post = -1;
    protected $post;
    protected $post_count;
    protected $posts_per_page = -1;
    protected $paging_links;

    const SETTING_NAMESPACE = 'thefold_fastpress';

    function __construct($engine='Solr')
    {
        $this->engine = Solr::get_instance(
            WordPress::get_option(self::SETTING_NAMESPACE,'path'),
            WordPress::get_option(self::SETTING_NAMESPACE,'host'),
            WordPress::get_option(self::SETTING_NAMESPACE,'port')
        ); 
    }

    function index_post(\WP_Post $post)
    {
        return $this->engine->index_post($post);
    }

    function get_posts($args=[]){
   
        $defaults = [
            'post_type'=>'post',
            'post_status'=>'published'
        ];

        if(!isset($args['posts_per_page'])){
	    $args['posts_per_page'] = get_option('posts_per_page');
        } elseif($args['posts_per_page'] == -1) {
            $args['nopaging'] = true;
        }

        $args = array_merge($defaults, $args);

        $this->posts_per_page = $args['posts_per_page'];
        $this->paging_links = null;

        return $this->engine->get_posts($args);
    }

    function query_posts($args)
    {
        $this->posts = $this->get_posts($args);

        $this->post_count = count($this->posts);
    }
    
    function the_post() {
        global $post, $wp_query;
        $wp_query->in_the_loop = true;

        if ( $this->current_post == -1 ) // loop has just started
            do_action_ref_array('loop_start', array(&$wp_query));

        $post = $this->next_post();
        setup_postdata($post);
    }

    function have_posts() {
        global $wp_query;
        if ( $this->current_post + 1 < $this->post_count ) {
            return true;
        } elseif ( $this->current_post + 1 == $this->post_count && $this->post_count > 0 ) {
            do_action_ref_array('loop_end', array(&$this));
            // Do some cleaning up after the loop
            $this->rewind_posts();
        }

        $wp_query->in_the_loop = false;
        return false;
    }

    function next_post() {

        $this->current_post++;

        $this->post = $this->posts[$this->current_post];
        return $this->post;
    }
    
    function rewind_posts() {
        $this->current_post = -1;
        if ( $this->post_count > 0 ) {
            $this->post = $this->posts[0];
        }
    }

    function delete_post($post_id)
    {
        return $this->engine->delete_post($post_id);
    }

    function admin_init()
    {
        Admin::get_instance(self::SETTING_NAMESPACE);
        $this->engine->admin_init();
    }

    function set_facets($facets=[])
    {
        return $this->engine->set_facets($facets); 
    }
    
    function get_facets($qparams=null)
    {
        return $this->engine->get_facets($qparams); 
    }

    function get_count()
    {
        return $this->engine->get_count();
    }

    function get_next_posts_link($label = null) {

        if ( null === $label )
		$label = __( 'Next Page &raquo;' );

        if($link = @$this->get_paging_links()['next']){
	    $attr = apply_filters( 'next_posts_link_attributes', '' );
            return "<a href=\"$link\" $attr>$label</a>";
        }
    }

    function get_previous_posts_link($label = null) {

        if ( null === $label )
            $label = __( '&laquo; Previous Page' );

        if($link = @$this->get_paging_links()['previous']){

	    $attr = apply_filters( 'previous_posts_link_attributes', '' );
            return "<a href=\"$link\" $attr>$label</a>";
        }
    }
   
    function get_paging_links(callable $format_function=null)
    {
        if($this->paging_links){
            return $this->paging_links;
        }

        $this->paging_links = [];

        if($this->posts_per_page == -1){
            return $this->paging_links; 
        }
        
        if(!$format_function){
            $format_function = function($url,$page,$qs){
                $qs['page'] = $page;
                return $url.'?'.http_build_query($qs);
            };
        }

        parse_str($_SERVER['QUERY_STRING'],$qs);

        //fixes array values in get strings
        $qs = str_replace('[0]','[]',$qs);

        $url = $_SERVER['REQUEST_URI'];

        if($pos = strpos($url,'?')){
            $url = substr($url,0,$pos);
        }

        $current_page = isset($qs['page']) ? $qs['page'] : 1;
        $total_pages = ceil($this->get_count() / $this->posts_per_page);

        if($total_pages > 1){

            if ($previous = $current_page -1) {
                $this->paging_links['previous'] = $format_function($url,$previous,$qs);
            }

            for($i = max(1,$current_page-5); $i <= min($total_pages,$current_page+5); $i++) {
                $this->paging_links["$i"] = $format_function($url,$i,$qs);
            }

            $next_page = $current_page + 1;

            if ($next_page < $total_pages){
                $this->paging_links['next'] = $format_function($url,$next_page,$qs);
            }
        }

        return $this->paging_links;
    }
}
