<?php

namespace TheFold\FastPress;

use TheFold\Singleton;
use TheFold\WordPress\Setting;
use TheFold\WordPress;

class Admin
{
    use Singleton;
    
    const AJAX_INDEX = 'thefold_fastpress_index';
    const AJAX_DELETE_ALL = 'thefold_fastpress_delete_all';

    function __construct($setting_namespace) {

        $this->setting_namespace = $setting_namespace;

        /**
         * Create the Solr settings page in WP admin
         */
        $indexing = new Setting\Section('indexing','Indexing', null, $this->setting_namespace);
        $connection = new Setting\Section('connection','Connection', null, $this->setting_namespace);

        new Setting\Page($this->setting_namespace,'FastPress',[

            new Setting\Field('path', 'Path', $connection),
            new Setting\Field('host', 'Host', $connection),
            new Setting\Field('port', 'Port', $connection),

            new Setting\Field('post_types', 'Post Types', $indexing, function($post_types,$field,$setting_group) {
                include __DIR__.'/views/post-types.php';
            }),
            
            new Setting\Field('post_status', 'Post Status', $indexing, function($post_status,$field,$setting_group) {
                if($post_status==''){
                    $post_status[] = 'publish';
                }
                include __DIR__.'/views/post-status.php';
            }),

            new Setting\Field('taxonomies', 'Taxonomies', $indexing, function($taxonomies,$field,$setting_group) {
                include __DIR__.'/views/taxonomies.php';
            }),

            new Setting\Field('custom_fields', 'Custom Fields', $indexing, function($custom_fields,$field,$setting_group,$options) {
                include __DIR__.'/views/custom-fields.php';
            }),

            new Setting\Field('indexed_at', '', $indexing, function($indexed_at) {
                include __DIR__.'/views/index-now.php';
            }),

        ]);

        $this->init_ajax();

    }

    function init_ajax() {

        add_action('admin_init',function() {

            /**
             * Ajax method called during Solr indexing
             */
            add_action('wp_ajax_'.self::AJAX_INDEX, function() {

                $page = empty($_POST['page']) ? 0 : $_POST['page'];
                $per_page = 50;
                $total= 0;
                $total_indexed = $page * $per_page;
                $post_types = WordPress::get_option($this->setting_namespace,'post_types')?:['post','page'];
                $stati = WordPress::get_option($this->setting_namespace,'post_status','publish');

                $posts = get_posts([
                    'posts_per_page'=>$per_page,
                    'offset'=> $total_indexed,
                    'post_type'=> $post_types,
                    'post_status'=> $stati,
                ]);
                
                foreach($post_types as $type){
                    
                    foreach($stati as $status){
                    
                        $total += wp_count_posts($type)->$status;
                    }
                }

                foreach($posts as $post){
                    \FastPress\index_post($post);
                }

                $page += 1;
                $total_indexed = $page * $per_page;

                wp_send_json([
                    'page' => $page,
                    'done' => $total < $total_indexed,
                    'percent' => round( $total_indexed / $total * 100),
                    'total' => $total,
                    'total_indexed' => $total_indexed,
                    ]);
            });

            add_action('wp_ajax_'.self::AJAX_DELETE_ALL, function() {

                $solr = Solr::get_instance();

                $result = $solr->deleteAll();

                wp_send_json([
                    'status'=>$result->getStatus(),
                        'time'=>$result->getQueryTime()
                        ]);
            });
        });

    }


}
