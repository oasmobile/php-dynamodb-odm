#! /usr/bin/env php
<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-19
 * Time: 11:07
 */

use Oasis\Mlib\ODM\Dynamodb\Console\ConsoleHelper;
use Symfony\Component\Console\Application;

$autoloadFiles = [
    __DIR__ . '/../vendor/autoload.php', // dev
    __DIR__ . '/../../../autoload.php' // prod
];

foreach ($autoloadFiles as $autoloadFile) {
    if (file_exists($autoloadFile)) {
        /** @noinspection PhpIncludeInspection */
        require_once $autoloadFile;
    }
}

$directories = [getcwd(), getcwd() . DIRECTORY_SEPARATOR . 'config'];
$configFile  = null;
foreach ($directories as $directory) {
    $configFile = $directory . DIRECTORY_SEPARATOR . 'odm-config.php';
    
    if (file_exists($configFile)) {
        break;
    }
}

if (!file_exists($configFile)) {
    $help = <<<'HELP'
You are missing an "odm-config.php" or "config/odm-config.php" file in your
project, which is required to get the DynamoDb ODM Console working. You can use the
following sample as a template:

<?php
use Oasis\Mlib\ODM\Dynamodb\Console\ConsoleHelper;

// replace with file to your own project bootstrap
require_once 'bootstrap.php';

// replace with your own mechanism to retrieve the item manager
$itemManager = GetItemManager();

return new ConsoleHelper($itemManager);

HELP;
    echo $help;
    exit(1);
}

if (!is_readable($configFile)) {
    echo 'Configuration file [' . $configFile . '] does not have read permission.' . "\n";
    exit(1);
}

/** @noinspection PhpIncludeInspection */
/** @var ConsoleHelper $consoleHelper */
$consoleHelper = require $configFile;

$cli = new Application('DynamoDb ODM Command Line Interface', '0.2.2');
$cli->setCatchExceptions(true);
$consoleHelper->addCommands($cli);
$cli->run();
