<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-18
 * Time: 17:43
 */

namespace Oasis\Mlib\ODM\Dynamodb\Console;

use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use Symfony\Component\Console\Helper\Helper;

class ItemManagerHelper extends Helper
{
    /** @var ItemManager */
    protected $itemManager;
    
    public function __construct(ItemManager $itemManager)
    {
        $this->itemManager = $itemManager;
    }
    
    /**
     * @return ItemManager
     */
    public function getItemManager()
    {
        return $this->itemManager;
    }
    
    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     */
    public function getName()
    {
        return "itemManager";
    }
}
