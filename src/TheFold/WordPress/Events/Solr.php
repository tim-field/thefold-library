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
            'fields'=>['speakers_i' => $speaker->ID],
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
            'fields'=>['events_i' => $event->ID],
            'sort' => ['starts_at_dt','desc'],
            'cache_key' => $cache ? __CLASS__.':'.__FUNCTION__.':'.$event->ID : null
            ]);

       return $speakers;
    }

    public static function related_events(\WP_Post $event)
    {
        $solr = SolrService::get_instance();

        $events = $solr->get_posts([
            'post_types' => Events::CPT_EVENT,
            'fields'=>['related_events_i' => $event->ID],
            'sort' => ['starts_at_dt','desc'],
            ]);

       return $events;
    }

    protected function index_p2p_relations()
    {
        add_filter('thefold_solr_post_mapping',function($post_mapping){

            $post_mapping['speakers_i'] = function($event){

                $speakers = null;

                if($event->post_type == Events::CPT_EVENT){

                    $speakers = [];

                    $speakers_posts = get_posts( array(
                        'connected_type' => Events::P2P_EVENT_SPEAKER,
                        'connected_items' => $event->ID,
                        'nopaging' => true,
                        'suppress_filters' => false
                    ));

                    foreach($speakers_posts as $speaker){
                        $speakers[] = $speaker->ID;
                    }
                }

                return $speakers;
            };
            
            $post_mapping['events_i'] = function($speaker){

                $events = null;

                if($speaker->post_type == Events::CPT_SPEAKER){

                    $events = [];

                    $event_posts = get_posts( array(
                        'connected_type' => Events::P2P_EVENT_SPEAKER,
                        'connected_items' => $speaker->ID,
                        'nopaging' => true,
                        'suppress_filters' => false
                    ));

                    foreach($event_posts as $event){
                        $events[] = $event->ID;
                    }
                }

                return $events;
            };
            
            $post_mapping['related_events_i'] = function($event){

                $related_events = null;

                if($event->post_type == Events::CPT_EVENT){

                    $related_events = [];

                    $event_posts = get_posts( array(
                        'connected_type' => Events::P2P_RELATED_EVENTS,
                        'connected_items' => $event->ID,
                        'nopaging' => true,
                        'suppress_filters' => false
                    ));

                    foreach($event_posts as $related_event){
                        $related_events[] = $related_event->ID;
                    }
                }

                return $related_events;
            };

            return $post_mapping;
        });
    }
}
