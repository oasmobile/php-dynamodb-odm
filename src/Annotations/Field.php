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
    const CAS_DISABLED  = 'disabled';
    const CAS_ENABLED   = 'enabled';
    const CAS_TIMESTAMP = 'timestamp';
    
    /**
     * @var string
     */
    public $name = null;
    /**
     * @var string
     * @Enum(value={"string", "number", "binary", "bool", "null", "list", "map"})
     */
    public $type = 'string';
    /**
     * Check and set type
     *
     * @var string
     * @Enum(value={"disabled", "enabled", "timestamp"})
     */
    public $cas = self::CAS_DISABLED;
}
