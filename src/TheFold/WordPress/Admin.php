<?php

namespace TheFold\WordPress;

use TheFold\WordPress\Setting;

abstract class Admin
{
    protected $section_id = 'settings';
    protected $section_title = 'Settings';
    protected $page;
    protected $fields = [];
    protected $capability = 'manage_options';

    abstract function get_page_name();

    abstract function init();

    abstract function get_settings_namespace();


    function add_field($id, $title, \Closure $display_callback=null, Setting\Section $section=null)
    {
        $this->fields[$id] = [
            'title' => $title,
            'id' => $id,
            'section' => $section,
            'callback' => $display_callback
        ];
    }

    function create_section($title,$id=null)
    {
        if(!$id) { $id = $title; }

        return new Setting\Section($id, $title, null, $this->get_settings_namespace());
    }

    function render(){

        $field_objects = [];

        foreach($this->fields as $data){
            $field_objects[] = new Setting\Field($data['id'], $data['title'], $this->get_section($data['section']), $data['callback']);
        }

        new Setting\Page($this->get_settings_namespace(), $this->get_page_name(), $field_objects, null, $this->capability);
    }

    function get_section(Setting\Section $section=null){

        if(!$section){
            $section = $this->get_default_section();
        }
        
        return $section;
    }

    function get_default_section(){
        
        static $section;

        if(!$section){

            $id = $this->section_id ?: $this->section_title;

            $section = new Setting\Section($this->section_id, $this->section_title, null, $this->get_settings_namespace());
        }

        return $section;
    }

    function get_setting($id,$default=null){
        return WordPress::get_option($this->get_settings_namespace(),$id,$default);
    }
    
}
