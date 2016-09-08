<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-03
 * Time: 17:04
 */

namespace Oasis\Mlib\ODM\Dynamodb\Annotations;

use Doctrine\Common\Annotations\Annotation\Enum;
use Doctrine\Common\Annotations\Annotation\Required;

/**
 * Class Field
 *
 * @Annotation
 */
class Field
{
    /**
     * @var string
     */
    public $name;
    /**
     * @var string
     * @Enum(value={"string", "number", "binary", "bool", "null", "list", "map"})
     * @Required()
     */
    public $type = 'string';
}
