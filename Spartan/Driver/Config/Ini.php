<?php
namespace Spartan\Core\Config\Driver;

class Ini{
    public function parse($config){
        if (is_file($config)) {
            return parse_ini_file($config, true);
        } else {
            return parse_ini_string($config, true);
        }
    }
}
