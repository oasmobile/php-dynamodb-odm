<?php
declare(strict_types=1);

namespace Oasis\Mlib\ODM\Dynamodb\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Field
{
    public const CAS_DISABLED  = 'disabled';
    public const CAS_ENABLED   = 'enabled';
    public const CAS_TIMESTAMP = 'timestamp';

    public function __construct(
        public ?string $name = null,
        public string $type = 'string',
        public string $cas = self::CAS_DISABLED,
        public bool $gsiKey = false,
    ) {}
}
