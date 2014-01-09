<?php
namespace TheFold;

class WordPress{
    
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

   /**
    * @deprecated
    *
    * render_template is better most of the time
    **/ 
    static function render_view($view, $view_params=array(),$dir)
    {
        extract($view_params);
        include $dir .'/views/'.$view;
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

        add_filter('rewrite_rules_array',function($rules) use ($url_callbacks) {

            global $wp_rewrite;

            foreach(array_keys($url_callbacks) as $look_for_in_url) {
                $newRule = array('^'.trim($look_for_in_url,'/').'/?$' => 'index.php?'.static::query_var_name($look_for_in_url).'=1');

                $rules = $newRule + $rules;
            }

            return $rules;
        });

        add_filter('query_vars',function($qvars) use ($url_callbacks) {
            
            foreach(array_keys($url_callbacks) as $look_for_in_url) {
                
                $var = static::query_var_name($look_for_in_url);
                $qvars[] = $var; 
            }
            return $qvars;
        });
        
        add_action( 'template_redirect', function() use ($url_callbacks) {

            global $wp_query;

            foreach($url_callbacks as $url_key => $callback) {

                if ( $wp_query->get( static::query_var_name($url_key) ) ){

                    $params = null;

                    preg_match('#'.trim($url_key,'/').'#',$_SERVER['REQUEST_URI'],$params);

                    $res = call_user_func_array($callback,$params);

                    if($res === false)
                        static::send_404();
                    else{
                        exit();
                    }
                }
            }
        },0);

        /* I think this is too heavy, should be done manually only
         * \add_filter('admin_init', function(){
            global $wp_rewrite;
            $wp_rewrite->flush_rules();
        });*/
    }

    static protected function query_var_name($rewrite) {
       static $cache;

       if(!isset($cache[$rewrite])){
           $cache[$rewrite] = md5($rewrite);// preg_replace('/\W/','',$rewrite);
       }

       return $cache[$rewrite]; 
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
    
    static public function get_option($namespace,$key=null,$default=null)
    {
        $options = get_option($namespace);

        if($key)
            $return = isset($options[$key]) ? $options[$key] : null;
        else
            $return = $options;

        return $return ?: $default;
    }

    static public function get_custom_fields($post_type=[])
    {
        global $wpdb;

        $where = '';

        if($post_type){
            $where = "
                LEFT JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->postmeta.post_id
                WHERE $wpdb->posts.post_type IN ('".implode("','",(array) $post_type)."') ";
        }
        
        $keys = $wpdb->get_col( "
            SELECT meta_key
            FROM $wpdb->postmeta
            $where
            GROUP BY meta_key
            HAVING meta_key NOT LIKE '\_%'
            ORDER BY meta_key" );

        return $keys;
    }

    static function send_404()
    {
        global $wp_query;
        status_header('404');
        $wp_query->set_404();
    }
}
