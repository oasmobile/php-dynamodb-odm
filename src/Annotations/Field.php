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
    /**
     * Indicate that this field is a GSI key. A GSI key can be null, but null value will be discarded when
     * inserting/updating the item.
     *
     * Introducing this field property will resolve the following bug: an item with null value for a GSI key cannot be
     * added. However, if the property is discarded from the object being added, the GSI table will not be affected at
     * all.
     *
     * @var bool
     */
    public $gsiKey = false;
}
