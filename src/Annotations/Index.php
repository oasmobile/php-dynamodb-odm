<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-18
 * Time: 21:19
 */

namespace Oasis\Mlib\ODM\Dynamodb\Annotations;

use Doctrine\Common\Annotations\Annotation\Required;
use Oasis\Mlib\AwsWrappers\DynamoDbIndex;
use Oasis\Mlib\AwsWrappers\DynamoDbItem;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\AnnotationParsingException;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;

/**
 * Class Index
 *
 * @Annotation
 * @package Oasis\Mlib\ODM\Dynamodb\Annotations
 */
class Index
{
    /**
     * @var string
     * @Required()
     */
    public $hash = '';
    /**
     * @var string
     */
    public $range = '';
    /**
     * @var string
     */
    public $name = '';
    
    public function __construct(array $values)
    {
        if (isset($values[0])) {
            $this->hash = $values[0];
            if (isset($values[1])) {
                $this->range = $values[1];
            }
            if (isset($values[2])) {
                $this->name = $values[2];
            }
        }
        elseif (isset($values['hash'])) {
            $this->hash = $values['hash'];
            if (isset($values['range'])) {
                $this->range = $values['range'];
            }
            if (isset($values['name'])) {
                $this->name = $values['name'];
            }
        }
        else {
            throw new AnnotationParsingException("Index must be constructed with an array of hash and range keys");
        }
    }
    
    public function getKeys()
    {
        $ret = [
            $this->hash,
        ];
        if ($this->range) {
            $ret[] = $this->range;
        }
        
        return $ret;
    }
    
    public function getDynamodbIndex(array $fieldNameMapping, array $attributeTypes)
    {
        $hash  = $fieldNameMapping[$this->hash];
        $range = $this->range ? $fieldNameMapping[$this->range] : '';
        
        if (!isset($attributeTypes[$hash])
            || ($range && !isset($attributeTypes[$range]))
        ) {
            throw new ODMException("Index key is not defined as Field!");
        }
        
        $hashType  = $attributeTypes[$hash];
        $rangeKey  = $range ? : null;
        $rangeType = $range ? $attributeTypes[$range] : 'string';
        $hashType  = constant(DynamoDbItem::class . '::ATTRIBUTE_TYPE_' . strtoupper($hashType));
        $rangeType = constant(DynamoDbItem::class . '::ATTRIBUTE_TYPE_' . strtoupper($rangeType));
        $idx       = new DynamoDbIndex($hash, $hashType, $rangeKey, $rangeType);
        if ($this->name) {
            $idx->setName($this->name);
        }
        
        return $idx;
    }
}
