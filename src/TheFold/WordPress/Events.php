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

    public static function get_instance()
    {
        if(!static::$instance){
            static::$instance = new static();
        }

        return static::$instance;
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
        new CustomPostType(static::CPT_EVENT, 'Event');
        new CustomPostType(static::CPT_VENUE, 'Venue');
        new CustomPostType(static::CPT_SPEAKER, 'Speaker');
        new CustomPostType(static::CPT_SPONSOR, 'Sponsor');

        new CustomTaxonomy(static::CAT_EVENT,'Category', static::CPT_EVENT, ['hierarchical'=>true]);
        new CustomTaxonomy(static::TAG_EVENT,'Tag', static::CPT_EVENT);

        new CustomTaxonomy(static::CAT_SPEAKER,'Category', static::CPT_SPEAKER,['hierarchical'=>true]);
        new CustomTaxonomy(static::TAG_SPEAKER,'Tag', static::CPT_SPEAKER);
    }

    protected function init_acf()
    {
        $template_dir = get_stylesheet_directory().'/ACF/';
        $plugin_dir = __DIR__.'/ACF/';

        foreach([static::CPT_EVENT, static::CPT_VENUE, static::CPT_SPEAKER, static::CPT_SPONSOR] as $cpt){

            $dir = file_exists($template_dir.$cpt.'.php') ? $template_dir.$cpt.'.php' : $plugin_dir.$cpt.'.php';

            if(file_exists($dir)) {
                require $dir; 
            }
        }
    }

    protected function init_p2p()
    {
        \p2p_register_connection_type([ 
            'name' => static::P2P_EVENT_SPEAKER,
            'from' => static::CPT_EVENT,
            'to' => static::CPT_SPEAKER
            ]);
        
        \p2p_register_connection_type([ 
            'name' => static::P2P_EVENT_VENUE,
            'from' => static::CPT_EVENT,
            'to' => static::CPT_VENUE
        ]);

        \p2p_register_connection_type([ 
            'name' => static::P2P_EVENT_SPONSOR,
            'from' => static::CPT_EVENT,
            'to' => static::CPT_SPONSOR
        ]);
    }
}
