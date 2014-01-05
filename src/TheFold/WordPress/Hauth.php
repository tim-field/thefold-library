<?php
namespace TheFold\WordPress;
use TheFold\WordPress;
use TheFold\Singleton;
use TheFold\WordPress\Events\Facebook\Import;

require_once $_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php';

class Hauth {

    use Singleton;

    const URL = 'thefold-hauth';
    const BASE_URL = 'hybridauth';
    const ACTION_FRESH_AUTH = 'thefold-hauth-fresh-authentication';
    const ACTION_READY = 'thefold-hauth-ready';

    protected $hauth;
    protected $service_name;
    
    public function __construct($hauth_config) {
        
        $this->hauth = new \Hybrid_Auth($hauth_config);
        $this->init_hooks();
        $this->init_urls();
    }

    public function get_user_profile($service_name, $create_connection=false)
    {
        if(!$create_connection) {

            if(!$this->hauth->isConnectedWith($service_name))
                return false;
        }

        $adapter = $this->get_adapter($service_name);

        return $adapter->getUserProfile();
    }

    public function get_adapter($service_name)
    {
        return $this->hauth->authenticate($service_name); // if we don't have authetication yet, this will redirect to hybridauth/
    }

    public function authenticate($service_name)
    {
        $wp_user = null;

        if ($user_profile = $this->get_user_profile($service_name, true)){

            if(!$wp_user = $this->get_wp_user_by_profile($user_profile,$service_name))
            {
                if (!empty($user_profile->email)) {

                    $wp_user = get_user_by('email', $user_profile->email);
                }
            }

            if($wp_user instanceof \WP_User) {

                $this->save_hauth($wp_user->ID);
                update_user_meta($wp_user->ID, get_meta_key($service_name), $user_profile->identifier);

                return $wp_user;
            }
            else
            {
                //Take to a signup page
                do_action(static::ACTION_FRESH_AUTH,$user_profile);
            }
        }

        return $wp_user;
    }

    protected function init_urls() {

        WordPress::init_url_access([

            static::URL.'/?(\w+)?' => function($request,$service_name) {

                $this->service_name = $service_name; // this will be used by out authenticate hook

                $redirect = empty($_GET['redirect']) ? null : $_GET['redirect'];

                $res = wp_signon(array('remember' => true));

                if(is_wp_error($res)){
                    //todo show facebook error 
                }

                if($redirect){
                    wp_redirect(site_url($redirect)); 
                }
            },

           static::BASE_URL => function(){

                \Hybrid_Endpoint::process();
            },
       ]);
    }

    protected function init_hooks() {

        add_action('wp_login', function($user_login,$user) {
            restore_hauth($user->ID);
        },10,2);

        add_action('user_register',function($user_id) {

            save_hauth($user_id);

            $providers = $this->hauth->getConnectedProviders();

            foreach($providers as $service_name){

                $user_profile = get_user_profile($service_name);

                \update_user_meta($user_id, get_meta_key($service_name), $user_profile->identifier);
            }
        });

        add_filter('authenticate',function($user, $username, $password) {

            if ( is_a($user, 'WP_User')) { 

                return $user; 
            } 

            if( $this->service_name ) {

                if ( $username || $password ) {
                    return new \WP_Error('invalid','Not using hauth as username and password provided');
                }

                $wp_user = $this->authenticate($this->service_name);

                if( is_a($wp_user, 'WP_User' )) {

                    return $wp_user;
                }
            }

            return $user; 

        },999,3);
    }



  

    protected function get_wp_user_by_profile(\Hybrid_User_Profile $user_profile, $service_name) 
    {
        $user_id = $this->get_wp_user_id_by_servicename($service_name, $user_profile->identifier);

        return ($user_id) ? new \WP_User($user_id) : false;
    }

    protected function get_wp_user_id_by_servicename($service_name, $identifier)
    {
        global $wpdb;

        $user_id = $wpdb->get_var( $wpdb->prepare("
            SELECT u.ID FROM {$wpdb->usermeta} um 
            JOIN {$wpdb->users} u ON u.ID = um.user_id  
            WHERE um.meta_key = %s AND um.meta_value = %s", 
            $this->get_meta_key($service_name), $identifier) );

        return $user_id;
    }

    protected function get_meta_key($service_name) {
        return 'hauth_'.strtolower($service_name);
    }

    protected function save_hauth($user_id)
    {
        \update_user_meta( $user_id, 'hauth_session_data', $this->hauth->getSessionData() );
    }

    protected function restore_hauth($user_id)
    {
        if( $session_data = \get_user_meta($user_id, 'hauth_session_data', true) )
        {
            $this->hauth->restoreSessionData($session_data);
        }
    }

    protected function get_user_avatar($service_name)
    {
        if($user_profile = get_user_profile($service_name))
        {
            return $user_profile->photoURL;
        }
    }
}
