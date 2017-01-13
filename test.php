#! /usr/bin/env php
<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-03
 * Time: 17:01
 */

use Oasis\Mlib\ODM\Dynamodb\Console\ConsoleHelper;
use Oasis\Mlib\ODM\Dynamodb\Ut\Game;

require_once __DIR__ . "/vendor/autoload.php";

/** @var ConsoleHelper $consoleHelper */
$consoleHelper = require_once __DIR__ . "/odm-config.php";
//
//$app = new Application();
//$consoleHelper->addCommands($app);
//$app->run();
//
////$ret = preg_match_all('/#(?P<field>[a-zA-Z_][a-zA-Z0-9_]*)/', '#abca > 10 and #aa in (9, 10)', $matches);
////var_dump($ret);
////var_dump($matches);

$im = $consoleHelper->getItemManager();

//for ($i = 0; $i < 8; ++$i) {
//    $languages = ["pt", "de"];
//    $game      = new Game();
//    $game->setGamecode('demo-' . $i);
//    $game->setLanguage($languages[mt_rand(0, count($languages) - 1)]);
//    $game->setFamily('lo');
//    $im->persist($game);
//}
//$im->flush();
//
$im->getRepository(Game::class)->multiQueryAndRun(
    function ($item) {
        var_dump($item);
    },
    "languagePartition",
    ["pt", 'de'],
    '#lastUpdatedAt < :ts',
    [':ts' => time()],
    'language_partition-last_updated_at-index'
);
