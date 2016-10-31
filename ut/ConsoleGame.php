<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-10-31
 * Time: 16:17
 */

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\Annotations\Field;
use Oasis\Mlib\ODM\Dynamodb\Annotations\Item;

/**
 * Class ConsoleGame
 *
 * @Item(
 *     table="console_games",
 *     primaryIndex={"gamecode"},
 *     globalSecondaryIndices={
 *          {"family", "language"}
 *     }
 * )
 * @package Oasis\Mlib\ODM\Dynamodb\Ut
 */
class ConsoleGame extends Game
{
    /**
     * @var string
     * @Field()
     */
    protected $platform;
}
