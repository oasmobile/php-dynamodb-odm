#! /usr/bin/env php
<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-03
 * Time: 17:01
 */

use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use Oasis\Mlib\ODM\Dynamodb\User;

require_once __DIR__ . "/vendor/autoload.php";

//AnnotationRegistry::registerAutoloadNamespace("Oasis\\Mlib\\Dynamodb\\ODM\\Annotations", __DIR__ . "/src/Annotations/");
//AnnotationRegistry::registerFile(__DIR__ . "/src/Annotations/Field.php");
//AnnotationRegistry::registerLoader(
//    function ($c) {
//        if (class_exists($c)) {
//            return true;
//        }
//        else {
//            return false;
//        }
//    }
//);
//$reader = new AnnotationReader();
//$reader = new CachedReader(
//    $reader,
//    new FilesystemCache(__DIR__ . "/cache"),
//    true
//);
//
//$user        = new User();
//$rc          = new ReflectionClass(get_class($user));
//$annotations = $reader->getClassAnnotations($rc);
//var_dump($annotations);
//
//$properties = $rc->getProperties(~ReflectionProperty::IS_STATIC);
//foreach ($properties as $property) {
//    if ($property->isStatic()) {
//        continue;
//    }
//    echo "Property " . $property->getName() . PHP_EOL;
//    $annotations = $reader->getPropertyAnnotation($property, Field::class);
//    var_dump($annotations);
//}
//
//$obj = $rc->newInstanceWithoutConstructor();
//var_dump($obj);

$dynamodbConfig = [
    'region'  => 'ap-northeast-1',
    'profile' => 'oasis-minhao',
];
$itemManager    = new ItemManager($dynamodbConfig, 'odm-', __DIR__ . "/cache");
$user           = $itemManager->getRepository(User::class)->get(['id' => 1]);
var_dump($user);

$users = $itemManager->getRepository(User::class)->scan("#id > :id", ["#id" => "id"], [":id" => 1]);
//$users = $itemManager->getRepository(User::class)->query(
//    "#hometown = :hometown AND #age > :age",
//    ["#hometown" => "hometown", '#age' => 'age'],
//    [":hometown" => '', ":age" => 10],
//    'hometown-age-index'
//);
var_dump($users);

/** @var User $user */
foreach ($users as $user) {
    $user->setHometown('beijing');
}

//$user = $itemManager->get(User::class, ['id' => 1]);
//$itemManager->remove($user);
$user = new User();
$user->setId('wow');
$user->setName('Robin');
$user->setAlias('Van Persie');
$user->setAge(24);
$itemManager->persist($user);

$itemManager->flush();
