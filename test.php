#! /usr/bin/env php
<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-03
 * Time: 17:01
 */

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\FilesystemCache;
use Oasis\Mlib\Dynamodb\ODM\ItemManager;
use Oasis\Mlib\Dynamodb\ODM\User;

require_once __DIR__ . "/vendor/autoload.php";

$itemManager = new ItemManager('us-east-1', 'abc_');

//AnnotationRegistry::registerAutoloadNamespace("Oasis\\Mlib\\Dynamodb\\ODM\\Annotations", __DIR__ . "/src/Annotations/");
//AnnotationRegistry::registerFile(__DIR__ . "/src/Annotations/Field.php");
AnnotationRegistry::registerLoader(
    function ($c) {
        if (class_exists($c)) {
            return true;
        }
        else {
            return false;
        }
    }
);
$reader = new AnnotationReader();
$reader = new CachedReader(
    $reader,
    new FilesystemCache(__DIR__ . "/cache"),
    true
);

$user        = new User();
$rc          = new ReflectionClass(get_class($user));
$annotations = $reader->getClassAnnotations($rc);
var_dump($annotations);

$properties = $rc->getProperties(~ReflectionProperty::IS_STATIC);
foreach ($properties as $property) {
    if ($property->isStatic()) {
        continue;
    }
    echo "Property " . $property->getName() . PHP_EOL;
    $annotations = $reader->getPropertyAnnotations($property);
    var_dump($annotations);
}

$obj = $rc->newInstanceWithoutConstructor();
var_dump($obj);
