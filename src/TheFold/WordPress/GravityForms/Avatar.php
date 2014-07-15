<?php
namespace TheFold\WordPress\GravityForms;
use TheFold\WordPress\Import;
use TheFold\WordPress\GravityForm;

class Avatar{

    protected $meta_key;
    protected $gform_field;

    const FILTER_FIELD_CONTENT = 'thefold-gforms-avatar-field-content';
    const FILTER_FIELD_AVATAR = 'thefold-gforms-avatar-field-avatar';
    const FILTER_AVATAR = 'thefold-gforms-avatar';

    function __construct($meta_key = 'avatar_attachment_id', $gform_field='avatar_image', $overide=false){

        $this->meta_key = $meta_key;
        $this->gform_field = $gform_field;
        $this->overide = $overide;

        $this->init_hooks();
    }
	
    /**
     * If using gravityforms user registration plugin, then this will return true
     * when viewing a create user form. This is useful to know when to not show
     * an avatar. i.e don't show one on create because they aren't going to have one yet.
     */
    function is_create_user_form($form_id){

        if(!class_exists('\GFUser')){
            return;
        }

        if($config = \GFUser::get_config($form_id))
        {
            return rgars($config, 'meta/feed_type') == 'create';    
        }
        
        return false;
    }


    protected function set_image($user_id, $form, $entry) {
        $gf = new GravityForm($form,$entry);

        if($file_path = $gf->getValue($this->gform_field))  {

            if($attachment_id = Import::create_attachment($file_path)) {

                update_user_meta( 
                    $user_id,
                    $this->meta_key, 
                    $attachment_id 
                );
            }
        }
    }

    protected function init_hooks()
    {

        //Note the form argument won't be passed unless you hack the registration plugin
        add_action( 'gform_user_registered', function($user_id, $user_config, $entry, $user_pass, $form){

            $this->set_image($user_id,$form,$entry);

        },10,5);

        //Note the form argument won't be passed unless you hack the registration plugin
        add_action( 'gform_user_updated', function($user_id, $user_config, $entry, $user_pass, $form){

            $this->set_image($user_id,$form,$entry);

        },10,5);

        add_filter( 'get_avatar', function($avatar, $user_id, $size, $default, $alt){

            //this should only be user_id or email. Bail otherwise.
            if(!is_numeric($user_id) && !is_string($user_id)){
                return $avatar;
            }

            $key = serialize($user_id).":".serialize($size);

            if($cache = get_transient( $key )){
                $avatar = $cache;
            }else{

                if(!is_numeric($user_id)){
                
                    $user = get_user_by('email',$user_id);
                    $user_id = $user->ID;
                }

                if(is_numeric($user_id)) {

                    if ($avatar && !$this->overide)
                        return $avatar;
                    elseif ($attachment_id = $this->get_src($user_id)) {

                        if( is_numeric($size) && $data = image_get_intermediate_size($attachment_id,array($size,$size))){

                            if ( empty($data['url']) && !empty($data['file']) ) {
                                $file_url = wp_get_attachment_url($attachment_id);
                                $data['path'] = path_join( dirname($imagedata['file']), $data['file'] );
                                $data['url'] = path_join( dirname($file_url), $data['file'] );
                            }

                            $avatar = "<img src='{$data['url']}' class='avatar' id='avatar-{$user_id}' width='$size' />";

                        }else{

                            $image = wp_get_attachment_image_src($attachment_id, $size, $icon);
                            if ( $image ) {
                                list($src, $width, $height) = $image;

                                $avatar = "<img src='{$src}' class='avatar' id='avatar-{$user_id}' />";
                            }
                        }
                        
                        set_transient( $key, $avatar, 14);

                    }
                }
            }

            return apply_filters(self::FILTER_AVATAR,$avatar,$user_id,$size,$default,$alt);
        
        },220,5);


        add_filter( 'gform_field_content',function($field_content,$field,$value,$lead_id,$form_id){

            if( ($field['adminLabel'] == $this->gform_field || $field['cssClass'] == $this->gform_field ) && !is_admin()){

                $field_id = $field['id'];
                $description = $field['description'];
                $avatar = '';

                    //don't show avatars when first creating a user
                if( ! $this->is_create_user_form($form_id)) {

                    $user_id = apply_filters('ecefolio_gform_profile_user_id',
                        get_current_user_id()
                    );

                    $avatar = apply_filters(
                        self::FILTER_FIELD_AVATAR,
                        get_avatar($user_id),
                        $user_id
                    );

                }

                $label = $field['label'];

                $field_content = "
                    <label class='gfield_label' for='input_{$form_id}_{$field_id}'>$label</label>

                    <div class='ginput_container'>
                        <input name='input_{$field_id}' id='input_{$form_id}_{$field_id}' type='file' value='' size='20' class='". (($avatar) ? "gform_hidden" : "") ." medium'  />";
                
                if($avatar){
                    $field_content .= "
                    <span class='ginput_preview'>$avatar | <a href='javascript:;' onclick='gformDeleteUploadedFile({$form_id}, {$field_id});'>delete</a></span> ";
                }

                $field_content .="
                    </div>

                    <div class='gfield_description'>$description</div> ";

            }

            return apply_filters(self::FILTER_FIELD_CONTENT,$field_content,$field,$value,$lead,$form_id);
        },10,5);

        //TODO

        add_action('acf/register_fields',function(){
            if(function_exists("register_field_group"))
            {
                register_field_group(array (
                    'id' => 'acf_avatar',
                    'title' => 'Logo',//hack for ecomail
                    'fields' => array (
                        array (
                            'key' => 'field_51f8aa8c1a2ec',
                            'label' => 'Avatar',
                            'name' => 'avatar_attachment_id',
                            'type' => 'image',
                            'save_format' => 'id',
                            'preview_size' => 'thumbnail',
                            'library' => 'all',
                        ),
                    ),
                    'location' => array (
                        array (
                            array (
                                'param' => 'user',
                                'operator' => '==',
                                'value' => 'all',
                                'order_no' => 0,
                                'group_no' => 0,
                            ),
                        ),
                    ),
                    'options' => array (
                        'position' => 'normal',
                        'layout' => 'no_box',
                        'hide_on_screen' => array (
                        ),
                    ),
                    'menu_order' => 0,
                ));
            }
        });
    }

    public function get_src($user_id)
    {
        return get_user_meta($user_id, $this->meta_key, true);
    }
}
