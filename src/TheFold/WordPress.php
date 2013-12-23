<?php
namespace TheFold;

class WordPress{
    

    static function render_view($view, $view_params=array(),$dir)
    {
        extract($view_params);
        include $dir .'/views/'.$view;
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

        get_template_part($slug, $name);
        
        if($return) 
            return ob_get_clean();
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
    
    static public function get_option($namespace,$key=null)
    {
        $options = get_option($namespace);

        if($key)
            $return = isset($options[$key]) ? $options[$key] : null;
        else
            $return = $options;

        return $return;
    }
}
