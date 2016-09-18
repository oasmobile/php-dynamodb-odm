#! /usr/bin/env php
<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-03
 * Time: 17:01
 */

use Oasis\Mlib\ODM\Dynamodb\Console\ConsoleHelper;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use Symfony\Component\Console\Application;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__ . "/vendor/autoload.php";

$config = Yaml::parse(file_get_contents(__DIR__ . "/ut/ut.yml"));
$aws    = $config['dynamodb'];

$im = new ItemManager($aws, 'odm-', __DIR__ . "/ut/cache");

//$refl = $im->getItemReflection(User::class);
//var_dump($refl);

$consoleHelper = new ConsoleHelper($im);
$app           = new Application();
$consoleHelper->addNamespace('Oasis\Mlib\ODM\Dynamodb\Ut', __DIR__ . "/ut");
$consoleHelper->addCommands($app);
$app->run();
