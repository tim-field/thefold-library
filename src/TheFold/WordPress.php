<?php
namespace TheFold;

class WordPress{
    

    static function render_template($slug, $name = null, $view_params=array(), $return=false,$default_path=null)
    {
        $done = false;

        if(is_array($slug)){
            extract($slug);
        }

        if($view_params)
        {
            global $wp_query;

            foreach($view_params as $key => $value){
                $wp_query->set($key, $value);
            }
        }

        if($return) ob_start();

        if($default_path && file_exists($default_path)) {

            $templates = [];
            $name = (string) $name;
            if ( '' !== $name )
                $templates[] = "{$slug}-{$name}.php";

            $templates[] = "{$slug}.php";

            if(! $done = locate_template($templates,true,false) ){

                $done = true; 
                load_template($default_path,false);
            }
        }

        if(!$done) {
            get_template_part($slug, $name);
        }

        if($return) 
            return ob_get_clean();
    }

    static function render_page($slug,$view_params=[],$layout='layouts/default',$return=false)
    {
        return static::render_template([
            'slug' => $layout,
            'view_params' => array_merge(
                ['content_for_layout' => static::render_template($slug,null,$view_params,true)],
                $view_params
            ),
            'return' => $return
        ]);
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

    static function init_url_access($url_callbacks, $priority = 5){

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

                    $wp_query->is_home = false;

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
        }, $priority );

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

    static function get_post_content($post_id)
    {
        return $post_id ? apply_filters('the_content', get_post_field('post_content', $post_id)) : null;
    }

    static function get_post_by_slug($slug, $type='post')
    {
        global $wpdb;
        $page = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type= %s", $slug, $type ) );
        if ( $page )
            return get_post( $page, $output );
    }
    

    /**
     * This function based on the core function get_category_parents but works for any taxonomy.
     * Used in map_taxonomies function
     */ 
    static function get_term_parents( $id, $taxonomy, $link = false, $separator = '/', $nicename = false, $visited = array() ) {
        $chain = '';
        $parent = get_term( $id, $taxonomy );
        if ( is_wp_error( $parent ) )
            return $parent;

        if ( $nicename )
            $name = $parent->slug;
        else
            $name = $parent->name;

        if ( $parent->parent && ( $parent->parent != $parent->term_id ) && !in_array( $parent->parent, $visited ) ) {
            $visited[] = $parent->parent;
            $chain .= static::get_term_parents( $parent->parent, $taxonomy, $link, $separator, $nicename, $visited );
        }

        if ( $link )
            $chain .= '<a href="' . get_term_link( $parent->slug, $taxonomy ) . '" title="' . esc_attr( sprintf( __( "View all posts in %s" ), $parent->name ) ) . '">'.$name.'</a>' . $separator;
        else
            $chain .= $name.$separator;

        return $chain;
    }
}
