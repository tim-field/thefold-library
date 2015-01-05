<?php

namespace TheFold\Page\Component;

use TheFold\WordPress\Export;

class Map extends \TheFold\Page\Component{

    protected $field = 'location_p';

    function subscribe(/*\TheFold\Publication*/ $publication)
    {
        parent::subscribe($publication);
        
        $publication->subscribe_query(function($query){

            if(isset($_GET[$this->get_name()]['bounds'])){
            
                $query['bounds'][$this->field] = urldecode($_GET[$this->get_name()]['bounds']);
            }

            return $query;
        });
    }

    function get_js_deps(){

        $deps = [
            'jquery',
            'acf-map',
            'map-location',
        ];

        if(wp_script_is('google-marker-clusterer-plus','registered')){
            $deps[] = 'google-marker-clusterer-plus';
        }

        return $deps;
    }

    function get_js_config(){

        $config = ['selector'=>'.acf-map'];
        
        if(wp_script_is('google-marker-clusterer-plus','registered')){
            $config['cluster'] = true;
        }

        return $config;
    }

    function render($view_params=[], $partial='partials/map')
    {
        \TheFold\Locations\map($this->posts, $partial, $view_params);
    }

    function json()
    {
        $format = [
            'lat' => function($post) {
                return get_post_meta($post->ID, 'location',true)['lat'];
            },
            'lng' => function($post) {
                return get_post_meta($post->ID, 'location',true)['lng'];
            },
            'post_id' => 'ID',
            'html' => function($post) {
                return $post->post_title;
            }
        ];

        $rows = [];
        $row = [];

        foreach($this->posts as $marker){

            foreach(Export::export_object($marker,$format) as $field => $value){
                $row[$field] = $value;
            };

            $rows[] = $row;
        }

        return $rows;
    }
}

