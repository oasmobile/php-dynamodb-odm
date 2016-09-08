<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-03
 * Time: 18:00
 */

namespace Oasis\Mlib\ODM\Dynamodb\Annotations;

use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Class Item
 *
 * @Annotation
 * @Target("CLASS")
 */
class Item
{
    /**
     * @var string
     * @Required()
     */
    public $table;
    /**
     * @var array
     */
    public $primaryIndex = [];
    /**
     * @var array
     */
    public $globalSecondaryIndices = [];
    /**
     * @var string
     */
    public $repository;
    
    //public function __construct(array $value)
    //{
    //    var_dump($value);
    //}
    
}
