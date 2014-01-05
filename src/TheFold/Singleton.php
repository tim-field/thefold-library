<?php
namespace TheFold;

trait Singleton
{
    protected static $instance;

    static function get_instance()
    {
        if(!static::$instance){

            $reflect = new \ReflectionClass(get_called_class());
            
            static::$instance = $reflect->newInstanceArgs(func_get_args()); 
        }

        return static::$instance;
    }
}
