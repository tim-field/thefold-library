<?php

namespace TheFold;

class Gearman{

    protected $client;
    protected static $instance;

    protected function __construct() {

        $this->client = new \GearmanClient();
        $this->client->addServer('localhost', 4730);
    }

    public static function get_instance() {
        
        if(!static::$instance){
            static::$instance = new self();
        }

        return static::$instance;
    }

    public function get_client()
    {
        return $this->client; 
    }
}
