<?php
namespace TheFold\WordPress;

abstract class QuickConfig
{
    public $type;
    public $name;
    public $info;
    
    function __construct($type, $name, $info=array()){
        $this->type = $type;
        $this->info = $info;
        $this->name = $name;
    }

    function __get($key) 
    {
        if(isset($this->info[$key]))
            return is_callable($this->info[$key]) ? $this->info[$key]() : $this->info[$key];

        if(is_callable(array($this,'default_'.$key)))
            return $this->{'default_'.$key}();

        return null;
    }

    protected function default_plural(){
        return $this->name.'s';
    }
    
    protected function default_slug(){
        return preg_replace('/[^a-z0-9]/','-', strtolower($this->plural));
    }
}
