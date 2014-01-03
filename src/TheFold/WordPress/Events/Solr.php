<?php

namespace TheFold\WordPress\Events;

use TheFold\WordPress\Events;
use TheFold\WordPress\Solr as SolrService;

class Solr
{
    protected static $instance;

    public static function init()
    {
        if(!static::$instance){
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function __construct()
    {
        $this->index_p2p_relations();
    }

    public static function events_by_speaker(\WP_Post $speaker, $cache=true)
    {
        $solr = SolrService::get_instance();

        $events = $solr->get_posts([
            'post_types' => Events::CPT_EVENT,
            'fields'=>['speakers_s' => $speaker->post_name],
            'sort' => ['starts_at_dt','desc'],
            'cache_key' => $cache ? __CLASS__.':'.__FUNCTION__.':'.$speaker->ID : null
            ]);

       return $events;
    }

    public static function speakers_by_event(\WP_Post $event, $cache = true)
    {
        $solr = SolrService::get_instance();
        
        $speakers = $solr->get_posts([
            'post_types' => Events::CPT_SPEAKER,
            'fields'=>['events_s' => $event->post_name],
            'sort' => ['starts_at_dt','desc'],
            'cache_key' => $cache ? __CLASS__.':'.__FUNCTION__.':'.$event->ID : null
            ]);

       return $speakers;
    }

    protected function index_p2p_relations()
    {
        add_filter('thefold_solr_post_mapping',function($post_mapping){

            $post_mapping['speakers_s'] = function($event){

                $speakers = null;

                if($event->post_type == Events::CPT_EVENT){

                    $speakers = [];

                    $speakers_posts = get_posts( array(
                        'connected_type' => Events::P2P_EVENT_SPEAKER,
                        'connected_items' => $event,
                        'nopaging' => true,
                        'suppress_filters' => false
                    ));

                    foreach($speakers_posts as $speaker){
                        $speakers[] = $speaker->post_name;
                    }
                }

                return $speakers;
            };
            
            $post_mapping['events_s'] = function($speaker){

                $events = null;

                if($speaker->post_type == Events::CPT_SPEAKER){

                    $events = [];

                    $event_posts = get_posts( array(
                        'connected_type' => Events::P2P_EVENT_SPEAKER,
                        'connected_items' => $speaker,
                        'nopaging' => true,
                        'suppress_filters' => false
                    ));

                    foreach($event_posts as $event){
                        $events[] = $event->post_name;
                    }
                }

                return $events;
            };

            return $post_mapping;
        });
    }
}
