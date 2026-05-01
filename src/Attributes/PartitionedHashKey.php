<?php
declare(strict_types=1);

namespace Oasis\Mlib\ODM\Dynamodb\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class PartitionedHashKey
{
    public function __construct(
        public string $hashField,
        public string $baseField,
        public int $size = 16,
    ) {}
}
