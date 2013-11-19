<?php

namespace TheFold;

class WordPress{
    
    const LOG_LEVEL = KLogger::DEBUG;
    const LOG_FILE = 'plugin.log';
    protected static $log;

    static function render_view($view, $view_params=array())
    {
        extract($view_params);
        include static::plugin_dir() .'/views/'.$view;
    }

    static function render_template($slug, $name = null, $view_params=array(), $return=false)
    {
        if($view_params)
        {
            global $wp_query;

            foreach($view_params as $key => $value){
                $wp_query->query_vars[$key] = $value;
            }
        }

        if($return) ob_start();

        \get_template_part($slug, $name);
        
        if($return) 
            return ob_get_clean();
    }

    static function plugin_dir() {
        return realpath(__DIR__.'/../../');
    }


/*
 *
 * Example usage

 WordPress::init_url_access(array(
     'hello/[a-z0-9_-]+' =>      function($request_uri) {
         echo 'hello '. $request_uri;
     })
);
 */

    static function init_url_access($url_callbacks){

        $query_var_name = function($rewrite) {
            return preg_replace('/\W/','',$rewrite);
        };

        \add_filter('rewrite_rules_array',function($rules) use ($url_callbacks, $query_var_name) {

            global $wp_rewrite;

            foreach(array_keys($url_callbacks) as $look_for_in_url) {
                $newRule = array('^'.trim($look_for_in_url,'/').'/?$' => 'index.php?'.$query_var_name($look_for_in_url).'=1');

                $rules = $newRule + $rules;
            }

            return $rules;
        });

        \add_filter('query_vars',function($qvars) use ($url_callbacks, $query_var_name) {
            
            foreach(array_keys($url_callbacks) as $look_for_in_url) {
                
                $var = $query_var_name($look_for_in_url);
                $qvars[] = $var; 
            }
            return $qvars;
        });
        
        \add_action( 'template_redirect', function() use ($url_callbacks, $query_var_name) {

            global $wp_query;

            //static::log($wp_query->query_vars);

            foreach($url_callbacks as $url_key => $callback) {

                if ( $wp_query->get( $query_var_name($url_key) ) ){

                    $callback( $_SERVER['REQUEST_URI'] );
                    exit();
                }
            }
        },0);

        \add_filter('admin_init', function(){
            global $wp_rewrite;
            $wp_rewrite->flush_rules();
        });
    }
   
    static function log($message,$level=KLogger::DEBUG)
    {
        if(!static::$log){
            static::$log = new KLogger(static::plugin_dir().'/'.static::LOG_FILE,static::LOG_LEVEL);
        }

        static::$log->Log( is_array($message) ? print_r($message,true) : $message,$level);
    }

    static function report_error($error)
    {
        //TODO email someone
        static::log($error, KLogger::ERROR);
    }

    static public function get_user_role($user=null)
    {
        if(is_numeric($user))
            $user = new WP_User($user);

        if(!$user)
            $user = wp_get_current_user();

        $user_roles = $user->roles;
        return $user_roles ? array_shift($user_roles) : null;
    }
}
