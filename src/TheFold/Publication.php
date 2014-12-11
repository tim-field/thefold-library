<?php

namespace TheFold;

abstract class Publication {

    const PREFIX = 'thefold-publication-';

    static function publish($name, \Closure $callback)
    {
        do_action(self::PREFIX.$name, $callback()); 
    }

    static function subscribe($name, \Closure $callback)
    {
        add_action(self::PREFIX.$name, $callback);
    }
}
