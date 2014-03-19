<?php
namespace TheFold\FastPress;

interface Engine {

    function index_post(\WP_Post $post);    

    function get_posts($args=[]);    

    function delete_post($post_id);    

    function delete_all();

    function admin_init();    

    function set_facets($facets=[]);

    function get_facets($qparams=null);
    
    function get_facet($name, $qparams=null, $reuse=true);

    function get_count();
}
