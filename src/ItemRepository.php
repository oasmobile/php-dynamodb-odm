<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-06
 * Time: 12:17
 */

namespace Oasis\Mlib\Dynamodb\ODM;

class ItemRepository
{
    protected $itemClass;
    protected $dynamodbTable;
    
    public function __construct($itemClass)
    {
        $this->itemClass = $itemClass;
    }
    
    public function get($keys)
    {
    }
    
    public function query() {
    }
    
    public function scan() {
    }
}
