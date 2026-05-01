<?php
/**
 * Script to create DynamoDB tables for integration tests.
 * Only registers the known test model classes (not scanning the whole ut/ directory).
 */

require_once __DIR__ . '/bootstrap.php';

use Oasis\Mlib\ODM\Dynamodb\DBAL\Schema\DynamoDbSchemaTool;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use Oasis\Mlib\ODM\Dynamodb\Ut\CasDemoUser;
use Oasis\Mlib\ODM\Dynamodb\Ut\ConsoleGame;
use Oasis\Mlib\ODM\Dynamodb\Ut\Game;
use Oasis\Mlib\ODM\Dynamodb\Ut\User;
use Oasis\Mlib\ODM\Dynamodb\Ut\UTConfig;

$im = new ItemManager(UTConfig::$dynamodbConfig, UTConfig::$tablePrefix);

$classes = [User::class, Game::class, ConsoleGame::class, CasDemoUser::class];
$reflections = [];
foreach ($classes as $class) {
    $reflections[$class] = $im->getItemReflection($class);
}

$conn = $im->createDBConnection();
$schemaTool = $conn->getSchemaTool($im, $reflections, function (string $msg) {
    echo $msg . "\n";
});

echo "Creating tables with prefix: " . UTConfig::$tablePrefix . "\n";
$schemaTool->createSchema(true, false);
echo "Done.\n";
