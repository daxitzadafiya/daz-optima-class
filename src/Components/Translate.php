<?php

namespace Daz\OptimaClass\Components;

class Translate
{ 
    public static function t($str){
       return __('app.' . strtolower($str));
    }
}