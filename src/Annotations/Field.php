<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-03
 * Time: 17:04
 */

namespace Oasis\Mlib\ODM\Dynamodb\Annotations;

use Doctrine\Common\Annotations\Annotation\Enum;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Class Field
 *
 * @Annotation
 * @Target("PROPERTY")
 */
class Field
{
    /**
     * @var string
     */
    public $name = null;
    /**
     * @var string
     * @Enum(value={"string", "number", "binary", "bool", "null", "list", "map"})
     */
    public $type = 'string';
}
