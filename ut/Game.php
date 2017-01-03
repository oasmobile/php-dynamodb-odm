<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-20
 * Time: 11:33
 */

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\Annotations\Field;
use Oasis\Mlib\ODM\Dynamodb\Annotations\Item;

/**
 * Class Game
 *
 * @Item(
 *     table="games",
 *     primaryIndex={"gamecode"},
 *     globalSecondaryIndices={
 *          {"family", "language"}
 *     }
 * )
 */
class Game
{
    /**
     * @var string
     * @Field()
     */
    protected $gamecode;
    /**
     * @var string
     * @Field()
     */
    protected $language;
    /**
     * @var string
     * @Field()
     */
    protected $family;
    /**
     * @var int
     * @Field(type="number", cas="timestamp")
     */
    protected $lastUpdatedAt;
    
    /**
     * @return string
     */
    public function getFamily()
    {
        return $this->family;
    }
    
    /**
     * @param string $family
     */
    public function setFamily($family)
    {
        $this->family = $family;
    }
    
    /**
     * @return string
     */
    public function getGamecode()
    {
        return $this->gamecode;
    }
    
    /**
     * @param string $gamecode
     */
    public function setGamecode($gamecode)
    {
        $this->gamecode = $gamecode;
    }
    
    /**
     * @return string
     */
    public function getLanguage()
    {
        return $this->language;
    }
    
    /**
     * @param string $language
     */
    public function setLanguage($language)
    {
        $this->language = $language;
    }
    
    /**
     * @return int
     */
    public function getLastUpdatedAt()
    {
        return $this->lastUpdatedAt;
    }
    
    /**
     * @param int $lastUpdatedAt
     */
    public function setLastUpdatedAt($lastUpdatedAt)
    {
        $this->lastUpdatedAt = $lastUpdatedAt;
    }
}
