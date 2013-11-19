<?php
namespace TheFold\WordPress\Setting;
use TheFold\WordPress\Setting\Section;

class Field
{
    public $name;
    public $title;
    public $display_callback;
    public $section;

    function __construct($name, $title, Section $section, $display_callback=null)
    {
        $this->name = $name;
        $this->title = $title;
        $this->display_callback = $display_callback;
        $this->section = $section;
    }

    function get_section() {
        return $this->section;
    }

    function get_display_callback($setting_group){

        $me = $this;

        if($this->display_callback) {
        
            return function() use ($setting_group, $me) { 
                
                $options = get_option($setting_group);
                $value = isset($options[$me->name]) ? $options[$me->name] : '';

                call_user_func($me->display_callback, $value, $me->name, $setting_group, $options);
            };
        
        } else {

            return function() use ($setting_group, $me) {
                
                $options = get_option($setting_group);
                $value = isset($options[$me->name]) ? $options[$me->name] : '';
                
                echo "<input id='{$setting_group}_{$me->name}' name='{$setting_group}[{$me->name}]' type='text' value='{$value}' />"; 
            };
        }
    }
}

