<?php

namespace TheFold\WordPress\Events;

use TheFold\WordPress\Events;
use TheFold\FastPress as FP;

class FastPress
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

    public static function events_by_speaker(\WP_Post $speaker, $params=[])
    {
        $default_params = [
            'post_type' => Events::CPT_EVENT,
            'fields'=>['speakers_p2p' => $speaker->ID],
            'sort' => ['starts_at_dt','desc'],
            ];

       return FP::get_instance()->get_posts(array_merge($default_params,$params));
    }

    public static function speakers_by_event(\WP_Post $event, $params=[])
    {
        $default_params = [
            'post_type' => Events::CPT_SPEAKER,
            'fields'=>['events_p2p' => $event->ID],
            'sort' => ['starts_at_dt','desc'],
            'cache_key' => $cache ? __CLASS__.':'.__FUNCTION__.':'.$event->ID : null
            ];

       return FP::get_instance()->get_posts(array_merge($default_params, $params));
    }

    public static function events_by_category($category_slug,$params=[])
    {
        $default_params = [
            'post_type'=> Events::CPT_EVENT,
            'sort' => ['starts_at_dt','desc'],
            'fields' => [Events::CAT_EVENT.'_taxonomy'=>$category_slug]
        ];

        if(isset($params['fields'])){
            $default_params['fields'] = array_merge($default_params['fields'], (array) $params['fields']);
            unset($params['fields']);
        }

        return FP::get_instance()->get_posts(array_merge_recursive($default_params, $params));
    }

    public static function related_events(\WP_Post $event,$params=[])
    {
        $default_params = [
            'post_type' => Events::CPT_EVENT,
            'fields'=>['related_events_p2p' => $event->ID],
            'sort' => ['starts_at_dt','desc']
            ];

       return FP::get_instance()->get_posts(array_merge($default_params, $params));
    }

    public static function get_featured($params=[]) {

        $solr = FP::get_instance();

        $default_params = [
            'fields'=>[
            'featured_b'=>true,
            '-archived_b' => true
            ],
        ];

        $params = array_merge($default_params, $params);

        $events = $solr->get_posts($params);

        return $events;
    }

    protected function index_p2p_relations()
    {
        add_filter('fastpress_post_mapping',function($post_mapping){

            $post_mapping['speakers_p2p'] = function($event){

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
            
            $post_mapping['events_p2p'] = function($speaker){

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
            
            $post_mapping['related_events_p2p'] = function($event){

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
