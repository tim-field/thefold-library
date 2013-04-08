<?php
namespace TheFold\WordPress;

class SettingField
{
    public $name;
    public $title;
    public $display_callback;
    public $section;

    function __construct($name,$title, callable $display_callback=null) //$section='default')
    {
        $this->name = $name;
        $this->title = $title;
        $this->display_callback = $display_callback;
        //$this->section = $section;
    }

    function get_display_callback($setting_group){

        $me = $this;

        if($this->display_callback) {
        
            return function() use ($setting_group, $me) { 
                
                $options = get_option($setting_group);
                $value = isset($options[$this->name]) ? $options[$this->name] : '';

                $me->display_callback($value, $this->name, $setting_group, $options); 
            };
        
        } else {
        
            return function() use ($setting_group) {
                
                $options = get_option($setting_group);
                $value = isset($options[$this->name]) ? $options[$this->name] : '';
                
                echo "<input id='{$setting_group}_{$this->name}' name='{$setting_group}[{$this->name}]' type='text' value='{$value}' />"; 
            };
        }
    }
}

