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
    
    /**
     * @var array
     * @Field(type="map")
     */
    protected $achievements;
    
    /**
     * @var array
     * @Field(type="list")
     */
    protected $authors;
    
    /**
     * @return array
     */
    public function getAchievements()
    {
        return $this->achievements;
    }
    
    /**
     * @param array $achievements
     */
    public function setAchievements($achievements)
    {
        $this->achievements = $achievements;
    }
    
    /**
     * @return array
     */
    public function getAuthors()
    {
        return $this->authors;
    }
    
    /**
     * @param array $authors
     */
    public function setAuthors($authors)
    {
        $this->authors = $authors;
    }
}
