<?php

namespace TheFold\WordPress\Events\Facebook;

use \TheFold\WordPress\Import as WordPressImport;
use \TheFold\WordPress\Events;

require_once $_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php';

class Import
{
    const LOGME = true;

    public function import_events(\Hybrid_Provider_Adapter $adapter, $force_update=false,$import_friends=true){

        $this->import_users_events($adapter,'me',$force_update); //import my events

        if($import_friends){
            $friends = $adapter->getUserContacts(); //import friends events

            foreach($friends as $friend){
                $this->import_users_events($adapter,$friend->identifier, $force_update);
            }
        }
    }

    public function import_users_events(\Hybrid_Provider_Adapter $adapter, $userid='me', $force_update=false){

        $qs = http_build_query(array(
            'since'=> 'NOW', 
            'fields'=>'id,name,privacy',
            'limit' => 100
        ));

        while(true){ 

            $events = $adapter->api()->api('/'.$userid.'/events?'.$qs);

            foreach($events['data'] as $event){
                $this->import_event($event,$adapter,$force_update); 
            }

            if(!empty($events['paging']['previous'])){
                $qs = parse_url($events['paging']['previous'],\PHP_URL_QUERY);
            }else{
                break;
            }
        }
    }

    protected function import_event($event,\Hybrid_Provider_Adapter $adapter, $force_update = false){

        if(empty($event['id']) || $event['privacy'] != 'OPEN') return null;

        $event['post_id'] = $this->entry_exists($event['id']);

        if($event['post_id'] && !$force_update) return $event['post_id'];

        $detail = array_merge($event,current($adapter->api()->api([
            'method' => 'fql.query',
            'query' => 'SELECT attending_count, unsure_count, venue, description, location, pic_cover FROM event WHERE eid = '.$event['id'],
        ])));

        if(static::LOGME) error_log('looking up event id '.$event['id'],3,__DIR__.'/fbimportlog.txt');

        $detail['venue_id'] = $this->import_venue($detail['venue'],$adapter,$force_update);

        $ID = WordPressImport::import_post(
            WordPressImport::map_data([
                        'ID' => 'post_id',
                        'post_title' => 'name',
                        'post_content' => 'description',
                        'post_date' => function($data){
                            return date('Y-m-d H:i:s',strtotime($data['start_time']));
                        },
                        'post_date_gmt' => function($data){
                            return gmdate('Y-m-d H:i:s',strtotime($data['start_time']));
                        },
                        'latitude' => function($data){ return $data['venue']['latitude']; },
                        'longitude' =>function($data){ return  $data['venue']['longitude']; },
                        'city' => function($data){ return  $data['venue']['city']; },
                        'venue_id' => function($data){ return  $data['venue_id']; },

                        'attending_count' => 'attending_count',
                        'unsure_count' => 'unsure_count',

                        'fb_id' => 'id',
                    ],
                    $detail
                    ),
                    Events::CPT_EVENT,
                    'publish'
        );

        $this->import_thumbnail($ID, $detail['pic_cover']['source'], $force_update);

        return $ID;
    }

    function import_venue($venue, \Hybrid_Provider_Adapter $adapter, $force_update=false){

        if(empty($venue['id'])) return null;

        $venue['post_id'] = $this->entry_exists($venue['id']);

        if($venue['post_id'] && !$force_update) return $venue['post_id'];

        $detail = array_merge($venue,$adapter->api()->api($venue['id']));

        $ID = WordPressImport::import_post(
            WordPressImport::map_data([
                    'ID' => 'post_id',
                    'post_title' => 'name',
                    'post_content' => 'about',
                    'tags_input' => function($data){ 

                        $cats = array_map(function($cat){ return $cat['name']; }, $data['category_list']);

                        $cats[] = $data['category'];

                        return array_unique($cats);
                    },
                    'latitude' => 'latitude',
                    'longitude' => 'longitude',
                    'city' => 'city',
                    'street' => 'street',
                    'post_code' => 'zip',
                    'country' => 'country',
                    'likes' => 'likes',
                    'link' => 'link',
                    'were_here_count' => 'were_here_count',
                    'talking_about_count' => 'talking_about_count',
                    'checkins' => 'checkins',
                    'hours' => 'hours',
                    'fb_id' => 'id',
                    ],
                    $detail
                ),
                Events::CPT_VENUE,
                'publish'
            );

        $this->import_thumbnail($ID, $detail['cover']['source'], $force_update);

        return $ID;
    }

    function import_thumbnail($ID, $path, $force_update = false){
        if(!has_post_thumbnail($ID) || $force_update){

            $attachment_id = WordPressImport::create_attachment($path);

            set_post_thumbnail($ID,$attachment_id);
        }
    }

    function entry_exists($fb_id)
    {
        global $wpdb;

        return $wpdb->get_var(sprintf( //todo maybe ensure post type is correct as well
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'fb_id' AND meta_value = '%d'",
            $fb_id)) ?: null;
    }
}
