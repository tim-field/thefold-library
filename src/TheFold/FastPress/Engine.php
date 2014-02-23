<?php
namespace TheFold\FastPress;

interface Engine {

    function index_post(\WP_Post $post);    

    function delete_post($post_id);    
    function get_posts($args=[]);    

    function admin_init();    

    function set_facets($facets=[]);
    function get_facets($qparams=null);

    function get_count();
}
