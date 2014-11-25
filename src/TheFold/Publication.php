<?php

namespace TheFold;

abstract class Publication {

    const PREFIX = 'thefold-publication-';

    function publish($name, \Closure $callback)
    {
        do_action(self::PREFIX.$name, $callback()); 
    }

    function subscribe($name, \Closure $callback)
    {
        add_action(self::PREFIX.$name,$callback);
    }
}
