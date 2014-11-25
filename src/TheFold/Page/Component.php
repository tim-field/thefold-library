<?php

namespace TheFold\Page;

abstract class Component
{
    function init_js(){}
    function render(){ return '';}
    function json(){ return []; }
}
