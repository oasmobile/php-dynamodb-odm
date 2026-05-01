<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 23/11/2017
 * Time: 4:42 PM
 */

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\Attributes\Field;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Index;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Item;

#[Item(
    table: 'games',
    primaryIndex: new Index(hash: 'gamecode'),
    projected: true,
)]
class BasicGameInfo
{
    #[Field()]
    protected $gamecode;

    #[Field()]
    protected $family;
    
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
}
