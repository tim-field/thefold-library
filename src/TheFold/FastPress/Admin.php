<?php

namespace TheFold\FastPress;

use TheFold\Singleton;
use TheFold\WordPress\Setting;
use TheFold\WordPress;

class Admin
{
    use Singleton;
    
    const AJAX_INDEX = 'thefold_fastpress_index';
    const AJAX_INDEX_USERS = 'thefold_fastpress_index_users';
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
            
            new Setting\Field('user_roles', 'Users', $indexing, function($user_roles,$field,$setting_group) {
                include __DIR__.'/views/users.php';
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
                $per_page = 52;
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
                
                if(!$total = get_transient('fastpress_index_count')){

                    foreach($post_types as $type){

                        foreach($stati as $status){

                            $total += wp_count_posts($type)->$status;
                        }

                        set_transient( 'fastpress_index_count', $total, 240 );
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

            add_action('wp_ajax_'.self::AJAX_INDEX_USERS, function() {
            
                $page = empty($_POST['page']) ? 0 : $_POST['page'];
                $per_page = 50;
                $total = 0;
                $total_indexed = $page * $per_page;
                $roles = WordPress::get_option($this->setting_namespace,'user_roles');

                if(!$roles){
                    wp_send_json([
                        'page' => $page,
                        'done' => true,
                        'percent' => 100,
                        'total' => 0,
                        'total_indexed' => 0,
                        ]);
                    return;
                }

                if(!$total = get_transient('fastpress_index_user_count')){

                    $user_count = count_users(); //todo cache this

                    foreach($roles as $role){
                        $total += $user_count['avail_roles'][$role];
                    }
                    
                    set_transient( 'fastpress_index_user_count', $total, 240 );
                }

                //http://wordpress.stackexchange.com/questions/39315/get-multiple-roles-with-get-users
                global $wpdb;

                $users = [];

                if($total) {

                    $params = [
                        'offset'=> $total_indexed,
                        'number' => $per_page
                    ];

                    if(count($roles) <= 1){

                        $params['role'] = current($roles);
                    } else {

                        //slow, but as fast as possible.
                        $params['meta_query'] = [[
                            'key' => $wpdb->get_blog_prefix(get_current_blog_id()) . 'capabilities',
                            'value' => '"(' . implode('|', array_map('preg_quote', $roles)) . ')"',
                            'compare' => 'REGEXP'
                        ]];
                    }

                    $users = get_users($params);
                }
                
                foreach($users as $user){
                    \FastPress\index_user($user);
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

                $query = empty($_POST['query']) ? null : $_POST['query'];

                $result = \FastPress\delete_all($query);

                wp_send_json([
                    'status'=>$result->getStatus(),
                    'time'=>$result->getQueryTime()
                ]);
            });
           
        });

    }

}
