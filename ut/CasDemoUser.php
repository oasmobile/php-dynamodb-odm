<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2017-05-11
 * Time: 21:58
 */

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\Attributes\Field;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Index;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Item;

#[Item(
    table: 'cas-demo-users',
    primaryIndex: new Index(hash: 'id'),
)]
class CasDemoUser
{
    #[Field(type: 'number', name: 'uid')]
    public $id = 0;

    #[Field(type: 'string')]
    public $name;

    #[Field(type: 'string', cas: Field::CAS_ENABLED)]
    public $ver;
}
