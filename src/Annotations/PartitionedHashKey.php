<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2017-01-12
 * Time: 19:06
 */

namespace Oasis\Mlib\ODM\Dynamodb\Annotations;

use Doctrine\Common\Annotations\Annotation\Required;

/**
 * Class Field
 *
 * @Annotation
 * @Target("PROPERTY")
 */
class PartitionedHashKey
{
    /**
     * size of partition
     *
     * @var int
     */
    public $size = 16;
    /**
     * the field this key will use as hashing source
     *
     * @var string
     * @Required()
     */
    public $hashField = null;
    /**
     * The field this key will use as a base value. A partitioned hash key is consist of the value of base field
     * appended with the hashed value of the hash field
     *
     * @var string
     * @Required()
     */
    public $baseField = null;
}
