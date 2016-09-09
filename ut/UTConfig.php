<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Symfony\Component\Yaml\Yaml;

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-09
 * Time: 10:33
 */
class UTConfig
{
    public static $dynamodbConfig = [];
    public static $tablePrefix    = 'odm-test-';
    
    public static function load()
    {
        $file = __DIR__ . "/ut.yml";
        $yml  = Yaml::parse(file_get_contents($file));
        
        self::$dynamodbConfig = $yml['dynamodb'];
        self::$tablePrefix    = $yml['prefix'];
    }
}
