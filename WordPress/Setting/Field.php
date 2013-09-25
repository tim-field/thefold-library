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
                $value = isset($options[$this->name]) ? $options[$this->name] : '';

                call_user_func($this->display_callback, $value, $this->name, $setting_group, $options);
            };
        
        } else {

            $that = $this;
        
            return function() use ($setting_group, $that) {
                
                $options = get_option($setting_group);
                $value = isset($options[$that->name]) ? $options[$that->name] : '';
                
                echo "<input id='{$setting_group}_{$that->name}' name='{$setting_group}[{$that->name}]' type='text' value='{$value}' />"; 
            };
        }
    }
}

