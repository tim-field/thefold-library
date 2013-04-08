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

\add_action( 'init', function() {
    init_url_access( array(

        '/filepickerIO/zencoderNotify/' => function($request){
            zencoder_notify();
        }
    ))
});
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

    static function setting_page($setting, $display_name, $setting_fields, $page_callback=null)
    {
        add_action('admin_menu', function() use($display_name, $setting, $page_callback ) {

            add_options_page($display_name, $display_name, 'manage_options', $setting, function() use ($page_callback, $setting, $display_name) {

                if($page_callback)
                    $page_callback();
                else { ?>
                    <div class="wrap">
                        <?php screen_icon(); ?>
                        <h2><?=$display_name?></h2>

                        <form method="post" action="options.php">
                            <?php settings_fields($setting); ?>
                            <?php do_settings_sections($setting); ?>
                            <input name="Submit" type="submit" value="Save Changes"/>
                        </form>
                    </div>
                <?php
                }
            });
        });

        add_action('admin_init', function() use($setting, $setting_fields){

            register_setting( $setting, $setting );

            add_settings_section($setting, 'Main Settings', function(){echo '<p>Main description of this section here</p>';}, $setting);

            foreach($setting_fields as $field) {
                add_settings_field($field->name, $field->title, $field->get_display_callback($setting), $setting, $setting);
                //todo records sections used ?
            }
        });
    }
}
