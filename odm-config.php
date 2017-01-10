<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-19
 * Time: 11:08
 */

use Oasis\Mlib\ODM\Dynamodb\Console\ConsoleHelper;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use Symfony\Component\Yaml\Yaml;

// replace with file to your own project bootstrap
require_once __DIR__ . '/ut/bootstrap.php';

// replace with your own mechanism to retrieve the item manager
$config = Yaml::parse(file_get_contents(__DIR__ . "/ut/ut.yml"));
$aws    = $config['dynamodb'];

$im = new ItemManager($aws, $config['prefix'], __DIR__ . "/ut/cache");
$im->addNamespace('Oasis\Mlib\ODM\Dynamodb\Ut', __DIR__ . "/ut");

return new ConsoleHelper($im);
