<?php

namespace TheFold\Page;

abstract class Component
{
    abstract static function get_name();
    function init_js(){}
    function render(){ return '';}
    function json(){ return []; }
}
