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
    const SETTING_NAMESPACE = 'thefold_fastpress';

    function __construct($engine='Solr'){
    
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

        return $this->engine->get_posts($args);
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
}
