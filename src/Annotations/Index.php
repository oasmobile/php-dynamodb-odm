<?php

namespace Oasis\Mlib\ODM\Dynamodb\Annotations;

use Oasis\Mlib\AwsWrappers\DynamoDbIndex;
use Oasis\Mlib\AwsWrappers\DynamoDbItem;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\AnnotationParsingException;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;

class Index
{
    public function __construct(
        public string $hash = '',
        public string $range = '',
        public string $name = '',
    ) {
        if ($this->hash === '') {
            throw new AnnotationParsingException(
                "Index must be constructed with a hash key"
            );
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
