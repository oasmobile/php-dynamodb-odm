<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-03
 * Time: 17:08
 */

namespace Oasis\Mlib\Dynamodb\ODM;

use Oasis\Mlib\Dynamodb\ODM\Annotations\Field;
use Oasis\Mlib\Dynamodb\ODM\Annotations\Item;

/**
 * Class User
 *
 * @Item(table="dmp-users", primaryIndex={"id"})
 * @package Oasis\Mlib\Dynamodb\ODM
 */
class User
{
    /**
     * @var int
     * @Field(type="number", key="sort")
     */
    protected $id = 0;
    /**
     * @var
     * @Field(type="string", name="")
     */
    protected $name;
    
    protected static $hello;
}
