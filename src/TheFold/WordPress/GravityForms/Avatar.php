<?php
namespace TheFold\WordPress\GravityForms;
use TheFold\WordPress\Import;
use TheFold\WordPress\GravityForm;


class Avatar{

    protected $meta_key;
    protected $gform_field;

    function __construct($meta_key = 'avatar_attachment_id', $gform_field='avatar_image', $overide=false){

        $this->meta_key = $meta_key;
        $this->gform_field = $gform_field;
        $this->overide = $overide;

        $this->init_hooks();
    }
// wp-content/plugins/emb-retailers/emb-retailers.php

    protected function init_hooks()
    {

        add_action( 'gform_after_submission', function($entry, $form){

            $gf = new GravityForm($form,$entry);

            if($file_path = $gf->getValue($this->gform_field)) {
                    
                if($attachment_id = Import::create_attachment($file_path)) {

                    update_user_meta( 
                        get_current_user_id(), 
                        $this->meta_key, 
                        $attachment_id 
                    );
                }
            }

        },10,2);


        add_filter( 'get_avatar', function($avatar, $user_id, $size, $default, $alt){

            if(is_numeric($user_id)) {

                if ($avatar && !$this->overide)
                    return $avatar;
                elseif ($attachment_id = get_user_meta($user_id, $this->meta_key, true))
                    return wp_get_attachment_image($attachment_id,array($size,$size));
            }

            return $avatar;

        },220,5);


        add_filter( 'gform_field_content',function($field_content,$field,$value,$lead_id,$form_id){

            if($field['adminLabel'] == $this->gform_field && !is_admin()){

                $field_id = $field['id'];
                $description = $field['description'];
                $avatar = get_avatar(get_current_user_id());
                $label = $field['label'];

                $field_content = "
                    <label class='gfield_label' for='input_{$form_id}_{$field_id}'>$label</label>
                    <div class='gfield_description'>$description</div>
                    <div class='ginput_container'>
                    <input name='input_{$field_id}' id='input_{$form_id}_{$field_id}' type='file' value='' size='20' class='gform_hidden medium'  /> 
                    <span class='ginput_preview'>$avatar | <a href='javascript:;' onclick='gformDeleteUploadedFile({$form_id}, {$field_id});'>delete</a></span>
                    </div>";

            }

            return $field_content;
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
                            'label' => 'Logo',//hack for ecomail
                            'name' => 'avatar_attachment_id',
                            'type' => 'image',
                            'save_format' => 'id',
                            'preview_size' => 'select-logo',//hack for ecomail
                            'library' => 'all',
                        ),
                    ),
                    'location' => array (
                        array (
                            array (
                                'param' => 'ef_user',
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
}
