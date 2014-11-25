<?php

namespace TheFold;

use TheFold\WordPress;

abstract class Dispatch {

    abstract function get_routes();

    function init(){
    
        WordPress::init_url_access($this->get_routes());

        add_action('init',function(){
            $this->maybe_flush_rewrites();
        },99);
    }

    function maybe_flush_rewrites() {

        $current = md5(json_encode(array_keys($this->get_routes())));

        $cached = get_option(get_called_class(), null );

        if ( empty( $cached ) ||  $current !== $cached ) {
            flush_rewrite_rules();
            update_option(get_called_class(), $current );
        }
    }
}
