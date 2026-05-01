<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-10-31
 * Time: 16:17
 */

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\Attributes\Field;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Index;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Item;

#[Item(
    table: 'console_games',
    primaryIndex: new Index(hash: 'gamecode'),
    globalSecondaryIndices: [
        new Index(hash: 'family', range: 'language'),
    ],
)]
class ConsoleGame extends Game
{
    #[Field()]
    protected $platform;
    
    #[Field(type: 'map')]
    protected $achievements;
    
    #[Field(type: 'list')]
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
