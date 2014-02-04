<?php

namespace TheFold\WordPress;

class Events
{
    protected static $instance;

    const CPT_EVENT = 'thefold-event';
    const CPT_VENUE = 'thefold-venue';
    const CPT_SPEAKER = 'thefold-speaker';
    const CPT_SPONSOR = 'thefold-sponsor';
    
    const TAG_EVENT = 'thefold-event-tag';
    const TAG_SPEAKER = 'thefold-speaker-tag';
   
    const CAT_EVENT = 'thefold-event-cat';
    const CAT_SPEAKER = 'thefold-speaker-cat';

    const P2P_EVENT_SPEAKER = 'thefold-event-to-speaker';
    const P2P_EVENT_VENUE = 'thefold-event-to-venue';
    const P2P_EVENT_SPONSOR = 'thefold-event-to-sponsor';
    const P2P_RELATED_EVENTS = 'thefold-related-events';

    public static function init()
    {
        if(!static::$instance){
            static::$instance = new static();
        }

        return static::$instance;
    }

    public static function get_venues(\WP_Post $event)
    {
        return get_posts( array(
          'connected_type' => static::P2P_EVENT_VENUE,
          'connected_items' => $event,
          'nopaging' => true,
          'suppress_filters' => false
        ));
    }

    public static function get_sponsors(\WP_Post $event)
    {
        return get_posts( array(
            'connected_type' => static::P2P_EVENT_SPONSOR,
            'connected_items' => $event,
            'nopaging' => true,
            'suppress_filters' => false
        ));
    }

    protected function __construct()
    {
       $this->init_cpt(); 
       $this->init_acf();
       
       add_action( 'p2p_init', function(){
            $this->init_p2p();
       });
    }

    protected function init_cpt()
    {
        new CustomPostType(static::CPT_EVENT, 'Event', ['menu_icon'=>'dashicons-calendar']);
        new CustomPostType(static::CPT_VENUE, 'Venue', ['menu_icon'=>'dashicons-location-alt']);
        new CustomPostType(static::CPT_SPEAKER, 'Speaker', ['menu_icon'=>'dashicons-groups']);
        new CustomPostType(static::CPT_SPONSOR, 'Sponsor', ['menu_icon'=>'dashicons-star-filled']);

        new CustomTaxonomy(static::CAT_EVENT,'Category', static::CPT_EVENT, ['hierarchical'=>true]);
        new CustomTaxonomy(static::TAG_EVENT,'Tag', static::CPT_EVENT);

        new CustomTaxonomy(static::CAT_SPEAKER,'Category', static::CPT_SPEAKER,['hierarchical'=>true]);
        new CustomTaxonomy(static::TAG_SPEAKER,'Tag', static::CPT_SPEAKER);
    }

    protected function init_acf()
    {
        $child_theme_dir = get_stylesheet_directory().'/acf/events.php';
        $theme_dir = get_template_directory().'/acf/events.php';
        $plugin_dir = __DIR__.'/acf/events.php';

        foreach([$child_theme_dir,$theme_dir,$plugin_dir] as $file) {

            if (file_exists($file)) {
                require $file;
                break;
            }
        }
    }

    protected function init_p2p()
    {
        p2p_register_connection_type([ 
            'name' => static::P2P_EVENT_SPEAKER,
            'from' => static::CPT_EVENT,
            'to' => static::CPT_SPEAKER
            ]);
        
        p2p_register_connection_type([ 
            'name' => static::P2P_EVENT_VENUE,
            'from' => static::CPT_EVENT,
            'to' => static::CPT_VENUE
        ]);

        p2p_register_connection_type([ 
            'name' => static::P2P_EVENT_SPONSOR,
            'from' => static::CPT_EVENT,
            'to' => static::CPT_SPONSOR
        ]);
        
        p2p_register_connection_type([
            'name' => static::P2P_RELATED_EVENTS,
            'from' => static::CPT_EVENT,
            'to' => static::CPT_EVENT,
            'reciprocal' => true,
            'title' => [
                'from'=>'Related Events',
                'to'=>'Related Events',
            ]
        ]);
    }
}
