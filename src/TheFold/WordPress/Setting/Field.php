<?php
namespace TheFold\WordPress\Setting;
use TheFold\WordPress\Setting\Section;

class Field
{
    public $name;
    public $title;
    public $display_callback;
    public $section;
    public $default;
    public $type;

    function __construct($name, $title=null, Section $section=null, $display_callback=null, $default=null,$type='text')
    {
        if(is_array($name)){

            $name += [
                'title' => null,
                'section' => null,
                'display_callback' => null,
                'default' => null,
                'type' => 'text'
            ];

            extract($name);
        }

        $this->name = $name;
        $this->title = $title;
        $this->display_callback = $display_callback;
        $this->section = $section;
        $this->default = $default;
        $this->type = $type;
    }

    function get_section() {
        return $this->section;
    }

    function get_display_callback($setting_group){

        if($this->display_callback && $this->display_callback instanceof \Closure) {
        
            return function() use ($setting_group) { 
                
                $options = get_option($setting_group);
                $value = isset($options[$this->name]) ? $options[$this->name] : '';

                call_user_func($this->display_callback, $value, $this->name, $setting_group, $options);
            };
        
        } else {

            return function() use ($setting_group) {
                
                $options = get_option($setting_group);
                $value = isset($options[$this->name]) ? $options[$this->name] : $this->default;

                switch($this->display_callback){
                    
                case 'textarea':
                    echo "<textarea rows=\"10\" cols=\"50\" id='{$setting_group}_{$this->name}' name='{$setting_group}[{$this->name}]' placeholder='{$this->default}'>{$value}</textarea>";
                    break;

                default: 
                    echo "<input id='{$setting_group}_{$this->name}' name='{$setting_group}[{$this->name}]' placeholder='{$this->default}' type='{$this->type}' value='{$value}' />"; 

                }
            };
        }
    }

    
}

